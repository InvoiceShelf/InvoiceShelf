<?php

namespace App\Domains\Sales\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Money\Application\ExchangeRateLookup;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Application\SerialNumberService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use App\Support\DocumentTaxes;
use App\Support\MinorUnits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns a description of an invoice or an estimate into exactly what the
 * document form would submit for it, so a caller that is not a browser (the
 * MCP server) never does the arithmetic itself.
 *
 * The intent names the customer, the lines and the few choices a person makes
 * on the form; everything the form would work out on its own is worked out
 * here the same way: the number, the dates, the template, the currency and
 * its rate, catalogue prices, every discount and every tax amount
 * ({@see DocumentTaxes}). The result goes through the same write request as a
 * form submission, so it is validated and stored the same way.
 *
 * Two checks are made here because the server does not make them: a tax type
 * must be one of the company's sales taxes, and every required custom field
 * must be answered.
 *
 * The intent, all keys optional unless noted:
 *
 * - `customer_id` (required on create);
 * - `date`, and `due_date` for an invoice or `expiry_date` for an estimate
 *   (`Y-m-d`; a due or expiry date may be null);
 * - `number`, `reference_number`, `notes`, `template`;
 * - `prices_include_tax`;
 * - `discount` and `discount_type` (`fixed` in major units, or `percentage`),
 *   when the company discounts whole documents;
 * - `tax_type_ids`, when the company taxes whole documents;
 * - `exchange_rate`, for a customer who pays in another currency;
 * - `custom_fields`: `[{slug or id, value}]`;
 * - `lines` (required on create): `[{item_id, name, description, quantity,
 *   unit_price (major units), unit, discount, discount_type, tax_type_ids,
 *   custom_fields}]`. A catalogue item fills in what the line leaves out.
 *
 * On an update the stored document supplies whatever the intent leaves out,
 * lines included, and a tax already on the document keeps the rate it was
 * applied at, as it does when the document is edited in the form.
 */
class SalesDocumentComposer
{
    private const SETTINGS = [
        'currency',
        'tax_per_item',
        'discount_per_item',
        'tax_included',
        'tax_included_by_default',
        'sales_tax_type',
        'sales_tax_address_type',
        'time_zone',
        'invoice_use_time',
        'invoice_set_due_date_automatically',
        'invoice_due_date_days',
        'estimate_set_expiry_date_automatically',
        'estimate_expiry_date_days',
    ];

    /** @var array<string, list<string>> */
    private array $errors = [];

    public function __construct(
        private readonly ExchangeRateLookup $exchangeRates,
    ) {}

    /**
     * What the invoice form would submit.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     *
     * @throws ValidationException naming every part of the intent that is wrong
     */
    public function invoice(array $intent, int $companyId, ?User $user = null, ?Invoice $invoice = null): array
    {
        return $this->compose('invoice', $intent, $companyId, $user, $invoice);
    }

    /**
     * What the estimate form would submit.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     *
     * @throws ValidationException naming every part of the intent that is wrong
     */
    public function estimate(array $intent, int $companyId, ?User $user = null, ?Estimate $estimate = null): array
    {
        return $this->compose('estimate', $intent, $companyId, $user, $estimate);
    }

    /**
     * @param  'invoice'|'estimate'  $kind
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function compose(string $kind, array $intent, int $companyId, ?User $user, Invoice|Estimate|null $existing): array
    {
        $this->errors = [];
        $existing?->loadMissing(['items.taxes', 'items.fields', 'taxes', 'fields']);

        $settings = CompanySetting::getSettings(self::SETTINGS, $companyId);
        $taxPerItem = $settings->get('tax_per_item') === 'YES';
        $discountPerItem = $settings->get('discount_per_item') === 'YES';

        $customer = $this->customer($intent['customer_id'] ?? $existing?->customer_id, $companyId);
        $homeCurrency = (int) $settings->get('currency');
        $currencyId = (int) ($customer?->currency_id ?: $homeCurrency);
        $rate = $currencyId === $homeCurrency ? null : $this->exchangeRate($intent, $currencyId, $companyId, $existing);

        $taxIncluded = $this->taxIncluded($intent, $settings, $existing);
        [$discount, $discountType] = $this->documentDiscount($intent, $discountPerItem, $existing);
        $snapshots = $this->taxSnapshots($existing);
        $lines = $this->lines($intent, $companyId, $taxPerItem, $discountPerItem, $rate, $snapshots, $existing);
        $documentRates = $this->documentRates($intent, $companyId, $taxPerItem, $snapshots, $existing);
        $customFields = $this->documentCustomFields($intent, $kind, $companyId, $existing);
        $template = $this->template($kind, $intent, $user, $existing);
        $date = $this->date($kind, $intent, $settings, $existing);
        $closingDate = $this->closingDate($kind, $intent, $settings, $date, $existing);

        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }

        $sums = DocumentTaxes::compose(
            array_map(fn (array $line) => [
                'price' => $line['price'],
                'quantity' => $line['quantity'],
                'discount' => $line['discount'],
                'discount_type' => $line['discount_type'],
                'taxes' => $line['rates'],
            ], $lines),
            $documentRates,
            $discount,
            $discountType,
            $taxPerItem,
            $discountPerItem,
            $taxIncluded,
        );

        $items = [];

        foreach ($lines as $index => $line) {
            $composed = $sums['lines'][$index];

            $items[] = [
                'item_id' => $line['item_id'],
                'name' => $line['name'],
                'description' => $line['description'],
                'unit_name' => $line['unit_name'],
                'quantity' => $line['quantity'],
                'price' => $line['price'],
                // The form holds a fixed discount in major units and submits
                // it multiplied by 100.
                'discount' => $line['discount_type'] === 'fixed' ? $line['discount'] * 100 : $line['discount'],
                'discount_type' => $line['discount_type'],
                'discount_val' => $composed['discount_val'],
                'tax' => $composed['tax'],
                'total' => $composed['total'],
                'taxes' => $composed['taxes'],
                'custom_fields' => $line['custom_fields'],
            ];
        }

        $payload = [
            'customer_id' => $customer->id,
            'currency_id' => $currencyId,
            "{$kind}_date" => $date,
            "{$kind}_number" => $intent['number'] ?? $existing?->{"{$kind}_number"} ?? $this->nextNumber($kind, $companyId, $customer->id),
            'reference_number' => array_key_exists('reference_number', $intent) ? $intent['reference_number'] : $existing?->reference_number,
            'template_name' => $template,
            'notes' => array_key_exists('notes', $intent) ? $intent['notes'] : $existing?->notes,
            'discount' => $discountType === 'fixed' ? $discount * 100 : $discount,
            'discount_type' => $discountType,
            'discount_val' => $sums['discount_val'],
            'tax_per_item' => $taxPerItem ? 'YES' : 'NO',
            'discount_per_item' => $discountPerItem ? 'YES' : 'NO',
            'tax_included' => $taxIncluded,
            'sales_tax_type' => $existing ? $existing->sales_tax_type : $settings->get('sales_tax_type'),
            'sales_tax_address_type' => $existing ? $existing->sales_tax_address_type : $settings->get('sales_tax_address_type'),
            'items' => $items,
            'taxes' => $sums['taxes'],
            'customFields' => $customFields,
            'sub_total' => $sums['sub_total'],
            'total' => $sums['total'],
            'tax' => $sums['tax'],
        ];

        $payload[$kind === 'invoice' ? 'due_date' : 'expiry_date'] = $closingDate;

        if ($rate !== null) {
            $payload['exchange_rate'] = $rate;
        }

        if (! $taxPerItem && $sums['taxes'] !== []) {
            $payload['tax_type_ids'] = array_column($sums['taxes'], 'tax_type_id');
        }

        return $payload;
    }

    private function customer(mixed $id, int $companyId): ?Customer
    {
        $customer = is_numeric($id)
            ? Customer::query()->where('company_id', $companyId)->find((int) $id)
            : null;

        if (! $customer) {
            $this->fail('customer_id', $id === null ? 'A customer is required.' : 'There is no customer with this id in the company.');
        }

        return $customer;
    }

    /**
     * The rate for a customer who pays in another currency: the one given,
     * else the one the document already has, else the one the form would
     * fetch.
     *
     * @param  array<string, mixed>  $intent
     */
    private function exchangeRate(array $intent, int $currencyId, int $companyId, Invoice|Estimate|null $existing): ?float
    {
        if (array_key_exists('exchange_rate', $intent) && $intent['exchange_rate'] !== null) {
            if (! is_numeric($intent['exchange_rate']) || (float) $intent['exchange_rate'] <= 0) {
                $this->fail('exchange_rate', 'The exchange rate must be a number above zero.');

                return null;
            }

            return (float) $intent['exchange_rate'];
        }

        if ($existing && (int) $existing->currency_id === $currencyId && (float) $existing->exchange_rate > 0) {
            return (float) $existing->exchange_rate;
        }

        $currency = Currency::find($currencyId);
        $rate = $currency ? $this->exchangeRates->rate($currency, $companyId) : null;

        if ($rate === null || $rate <= 0) {
            $this->fail('exchange_rate', sprintf(
                'The customer pays in %s and no exchange rate is available: give exchange_rate, the value of 1 %s in the company currency.',
                $currency?->code ?? 'another currency',
                $currency?->code ?? 'unit',
            ));

            return null;
        }

        return $rate;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function taxIncluded(array $intent, Collection $settings, Invoice|Estimate|null $existing): bool
    {
        if (! array_key_exists('prices_include_tax', $intent)) {
            return $existing
                ? (bool) $existing->tax_included
                : $settings->get('tax_included') === 'YES' && $settings->get('tax_included_by_default') === 'YES';
        }

        $included = filter_var($intent['prices_include_tax'], FILTER_VALIDATE_BOOLEAN);

        if ($included && $settings->get('tax_included') !== 'YES') {
            $this->fail('prices_include_tax', 'This company does not price with tax included.');
        }

        return $included;
    }

    /**
     * The document discount the way the form holds it: a fixed one in major
     * units, a percentage as a percent.
     *
     * @param  array<string, mixed>  $intent
     * @return array{0: float|int, 1: string}
     */
    private function documentDiscount(array $intent, bool $discountPerItem, Invoice|Estimate|null $existing): array
    {
        $given = array_key_exists('discount', $intent) && $intent['discount'] !== null;

        if ($discountPerItem) {
            if ($given && (float) $intent['discount'] != 0) {
                $this->fail('discount', 'This company discounts each line: put the discount on the lines.');
            }

            return [0, 'fixed'];
        }

        if (! $given) {
            if ($existing) {
                $type = $existing->discount_type === 'percentage' ? 'percentage' : 'fixed';

                return [$type === 'fixed' ? $existing->discount / 100 : (float) $existing->discount, $type];
            }

            return [0, 'fixed'];
        }

        $type = $intent['discount_type'] ?? 'fixed';

        return [$this->discountValue($intent['discount'], $type, 'discount'), $type];
    }

    private function discountValue(mixed $value, mixed $type, string $path): float|int
    {
        if (! in_array($type, ['fixed', 'percentage'], true)) {
            $this->fail(str_replace('discount', 'discount_type', $path), 'The discount type is fixed or percentage.');

            return 0;
        }

        if (! is_numeric($value) || (float) $value < 0 || ($type === 'percentage' && (float) $value > 100)) {
            $this->fail($path, $type === 'percentage'
                ? 'A percentage discount is a number from 0 to 100.'
                : 'A fixed discount is an amount in major units, 0 or more.');

            return 0;
        }

        return $value + 0;
    }

    /**
     * The tax rows already on the document, by tax type, so a tax that stays
     * keeps the rate it was applied at.
     *
     * @return array{document: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>}
     */
    private function taxSnapshots(Invoice|Estimate|null $existing): array
    {
        $snapshots = ['document' => [], 'lines' => []];

        if (! $existing) {
            return $snapshots;
        }

        foreach ($existing->taxes as $tax) {
            $snapshots['document'][(int) $tax->tax_type_id] ??= $this->snapshot($tax);
        }

        foreach ($existing->items as $item) {
            foreach ($item->taxes as $tax) {
                $snapshots['lines'][(int) $tax->tax_type_id] ??= $this->snapshot($tax);
            }
        }

        return $snapshots;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Tax $tax): array
    {
        return [
            'tax_type_id' => (int) $tax->tax_type_id,
            'name' => $tax->name,
            'percent' => $tax->percent,
            'calculation_type' => $tax->calculation_type,
            'fixed_amount' => $tax->fixed_amount,
            'compound_tax' => (bool) $tax->compound_tax,
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array{document: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>}  $snapshots
     * @return list<array<string, mixed>>
     */
    private function lines(array $intent, int $companyId, bool $taxPerItem, bool $discountPerItem, ?float $rate, array $snapshots, Invoice|Estimate|null $existing): array
    {
        if (! array_key_exists('lines', $intent) && $existing) {
            return $existing->items->map(fn ($item) => $this->storedLine($item))->all();
        }

        if (! is_array($intent['lines'] ?? null) || $intent['lines'] === []) {
            $this->fail('lines', 'A document needs at least one line.');

            return [];
        }

        $required = $this->requiredFields('Item', $companyId);
        $lines = [];

        foreach (array_values($intent['lines']) as $index => $line) {
            $path = "lines.{$index}";

            if (! is_array($line)) {
                $this->fail($path, 'Each line is an object.');

                continue;
            }

            $item = null;

            if (isset($line['item_id'])) {
                $item = Item::query()->with(['unit', 'taxes'])->where('company_id', $companyId)->find($line['item_id']);

                if (! $item) {
                    $this->fail("{$path}.item_id", 'There is no item with this id in the company.');
                }
            }

            $name = $line['name'] ?? $item?->name;

            if (! is_string($name) || trim($name) === '') {
                $this->fail("{$path}.name", 'A line needs a name, or an item_id to take it from.');
            }

            $quantity = $line['quantity'] ?? 1;

            if (! is_numeric($quantity)) {
                $this->fail("{$path}.quantity", 'The quantity is a number.');
                $quantity = 0;
            }

            $price = $this->linePrice($line, $item, $rate, $path);

            [$discount, $discountType] = [0, 'fixed'];
            $lineDiscountGiven = isset($line['discount']) && (float) $line['discount'] != 0;

            if ($discountPerItem) {
                $discountType = $line['discount_type'] ?? 'fixed';
                $discount = $this->discountValue($line['discount'] ?? 0, $discountType, "{$path}.discount");
            } elseif ($lineDiscountGiven) {
                $this->fail("{$path}.discount", 'This company discounts whole documents: put the discount on the document.');
            }

            $rates = [];

            if ($taxPerItem) {
                $rates = array_key_exists('tax_type_ids', $line)
                    ? $this->rates($line['tax_type_ids'], $companyId, $snapshots['lines'], "{$path}.tax_type_ids", perLine: true)
                    : $this->itemRates($item);
            } elseif (! empty($line['tax_type_ids'])) {
                $this->fail("{$path}.tax_type_ids", 'This company taxes whole documents: put tax_type_ids on the document.');
            }

            $customFields = $this->answers($line['custom_fields'] ?? [], "{$path}.custom_fields");
            $this->requireAnswers($required, $customFields, "{$path}.custom_fields");

            $lines[] = [
                'item_id' => $item?->id,
                'name' => $name,
                'description' => array_key_exists('description', $line) ? $line['description'] : $item?->description,
                'unit_name' => $line['unit'] ?? $item?->unit?->name,
                'quantity' => $quantity + 0,
                'price' => $price,
                'discount' => $discount,
                'discount_type' => $discountType,
                'rates' => $rates,
                'custom_fields' => $customFields,
            ];
        }

        return $lines;
    }

    /**
     * A line kept from the stored document, the way the edit form loads it.
     *
     * @return array<string, mixed>
     */
    private function storedLine(Model $item): array
    {
        $type = $item->discount_type === 'percentage' ? 'percentage' : 'fixed';

        return [
            'item_id' => $item->item_id,
            'name' => $item->name,
            'description' => $item->description,
            'unit_name' => $item->unit_name,
            'quantity' => $item->quantity + 0,
            'price' => (int) $item->price,
            'discount' => $type === 'fixed' ? $item->discount / 100 : $item->discount + 0,
            'discount_type' => $type,
            'rates' => $item->taxes->map(fn (Tax $tax) => $this->snapshot($tax))->values()->all(),
            'custom_fields' => $item->fields
                ->map(fn ($answer) => ['id' => $answer->custom_field_id, 'value' => $answer->defaultAnswer])
                ->values()
                ->all(),
        ];
    }

    /**
     * A line's unit price in minor units: the one given, else the catalogue
     * item's, converted into the customer's currency the way the form does.
     *
     * @param  array<string, mixed>  $line
     */
    private function linePrice(array $line, ?Item $item, ?float $rate, string $path): int
    {
        if (array_key_exists('unit_price', $line) && $line['unit_price'] !== null) {
            $price = MinorUnits::fromMajor($line['unit_price']);

            if ($price === null) {
                $this->fail("{$path}.unit_price", 'The unit price is an amount in major units with at most two decimals, like "120" or "19.99".');

                return 0;
            }

            return $price;
        }

        if ($item) {
            return $rate ? DocumentTaxes::round($item->price / $rate) : (int) $item->price;
        }

        $this->fail("{$path}.unit_price", 'A line needs a unit_price, or an item_id to take it from.');

        return 0;
    }

    /**
     * The tax rows a catalogue item brings to a line, as the form copies
     * them when the item is picked.
     *
     * @return list<array<string, mixed>>
     */
    private function itemRates(?Item $item): array
    {
        if (! $item) {
            return [];
        }

        return $item->taxes
            ->filter(fn (Tax $tax) => (int) $tax->tax_type_id > 0)
            ->map(fn (Tax $tax) => $this->snapshot($tax))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array{document: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>}  $snapshots
     * @return list<array<string, mixed>>
     */
    private function documentRates(array $intent, int $companyId, bool $taxPerItem, array $snapshots, Invoice|Estimate|null $existing): array
    {
        if ($taxPerItem) {
            if (! empty($intent['tax_type_ids'])) {
                $this->fail('tax_type_ids', 'This company taxes each line: put tax_type_ids on the lines.');
            }

            return [];
        }

        if (! array_key_exists('tax_type_ids', $intent)) {
            return array_values($snapshots['document']);
        }

        return $this->rates($intent['tax_type_ids'], $companyId, $snapshots['document'], 'tax_type_ids', perLine: false);
    }

    /**
     * Tax rows for the given tax types, each checked to be one of the
     * company's sales taxes. A type already on the document keeps its stored
     * rate. A line row carries its percent or its fixed amount alone, the
     * way the line form sets it.
     *
     * @param  array<int, array<string, mixed>>  $snapshots
     * @return list<array<string, mixed>>
     */
    private function rates(mixed $ids, int $companyId, array $snapshots, string $path, bool $perLine): array
    {
        if ($ids === null) {
            return [];
        }

        if (! is_array($ids) || array_filter($ids, fn ($id) => ! is_numeric($id)) !== []) {
            $this->fail($path, 'tax_type_ids is a list of tax type ids.');

            return [];
        }

        $ids = array_map('intval', array_values($ids));

        if (count($ids) !== count(array_unique($ids))) {
            $this->fail($path, 'A tax type can be applied only once.');

            return [];
        }

        $types = TaxType::query()
            ->where('company_id', $companyId)
            ->where('type', TaxType::TYPE_GENERAL)
            ->where(fn ($query) => $query
                ->whereNull('transaction_type')
                ->orWhere('transaction_type', TaxType::TRANSACTION_TYPE_SALES))
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rates = [];

        foreach ($ids as $position => $id) {
            if (isset($snapshots[$id])) {
                $rates[] = $snapshots[$id];

                continue;
            }

            $type = $types->get($id);

            if (! $type) {
                $this->fail("{$path}.{$position}", "Tax type {$id} is not one of the company's sales taxes.");

                continue;
            }

            $fixed = $type->calculation_type === 'fixed';

            $rates[] = [
                'tax_type_id' => $type->id,
                'name' => $type->name,
                'percent' => $perLine && $fixed ? null : $type->percent,
                'calculation_type' => $type->calculation_type,
                'fixed_amount' => $perLine ? ($fixed ? $type->fixed_amount : 0) : $type->fixed_amount,
                'compound_tax' => (bool) $type->compound_tax,
            ];
        }

        return $rates;
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return list<array<string, mixed>>
     */
    private function documentCustomFields(array $intent, string $kind, int $companyId, Invoice|Estimate|null $existing): array
    {
        if (array_key_exists('custom_fields', $intent)) {
            $answers = $this->answers($intent['custom_fields'], 'custom_fields');
        } else {
            $answers = $existing
                ? $existing->fields
                    ->map(fn ($answer) => ['id' => $answer->custom_field_id, 'value' => $answer->defaultAnswer])
                    ->values()
                    ->all()
                : [];
        }

        $this->requireAnswers($this->requiredFields(ucfirst($kind), $companyId), $answers, 'custom_fields');

        return $answers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function answers(mixed $answers, string $path): array
    {
        if (! is_array($answers)) {
            $this->fail($path, 'custom_fields is a list of {slug, value} answers.');

            return [];
        }

        $valid = [];

        foreach (array_values($answers) as $index => $answer) {
            if (! is_array($answer) || (! isset($answer['slug']) && ! isset($answer['id'])) || ! array_key_exists('value', $answer)) {
                $this->fail("{$path}.{$index}", 'Each answer names its field by slug (or id) and has a value.');

                continue;
            }

            $valid[] = array_intersect_key($answer, array_flip(['id', 'slug', 'value']));
        }

        return $valid;
    }

    /**
     * @return Collection<int, CustomField>
     */
    private function requiredFields(string $modelType, int $companyId): Collection
    {
        return CustomField::query()
            ->where('company_id', $companyId)
            ->where('model_type', $modelType)
            ->where('is_required', true)
            ->get(['id', 'slug', 'label']);
    }

    /**
     * @param  Collection<int, CustomField>  $required
     * @param  list<array<string, mixed>>  $answers
     */
    private function requireAnswers(Collection $required, array $answers, string $path): void
    {
        foreach ($required as $field) {
            $answered = collect($answers)->contains(fn (array $answer) => ((isset($answer['id']) && (int) $answer['id'] === $field->id)
                    || (isset($answer['slug']) && $answer['slug'] === $field->slug))
                && $answer['value'] !== null && $answer['value'] !== '');

            if (! $answered) {
                $this->fail($path, "The custom field \"{$field->label}\" (slug {$field->slug}) is required.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function template(string $kind, array $intent, ?User $user, Invoice|Estimate|null $existing): ?string
    {
        $available = array_column(PdfTemplateUtils::getFormattedTemplates($kind, ''), 'name');

        if (isset($intent['template'])) {
            if (! in_array($intent['template'], $available, true)) {
                $this->fail('template', 'The template is one of: '.implode(', ', $available).'.');
            }

            return $intent['template'];
        }

        if ($existing?->template_name) {
            return $existing->template_name;
        }

        $preferred = $user?->getSettings(["default_{$kind}_template"])->get("default_{$kind}_template");

        return $preferred ?: ($available[0] ?? null);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function date(string $kind, array $intent, Collection $settings, Invoice|Estimate|null $existing): ?string
    {
        $withTime = $kind === 'invoice' && $settings->get('invoice_use_time') === 'YES';

        if (isset($intent['date'])) {
            $date = $this->parseDate($intent['date'], 'date', $withTime);

            // A company that stamps invoices with a time gets the current one
            // when only the day is given, as the form does.
            return $date !== null && $withTime && strlen($date) === 10
                ? "{$date} ".$this->today($settings)->format('H:i')
                : $date;
        }

        if ($existing) {
            $stored = $existing->getRawOriginal("{$kind}_date");

            return $stored ? CarbonImmutable::parse($stored)->format($withTime ? 'Y-m-d H:i' : 'Y-m-d') : null;
        }

        return $this->today($settings)->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');
    }

    /**
     * The due date of an invoice or the expiry date of an estimate: the one
     * given (null clears it), else the stored one, else the company's
     * automatic one counted from the document date.
     *
     * @param  array<string, mixed>  $intent
     */
    private function closingDate(string $kind, array $intent, Collection $settings, ?string $date, Invoice|Estimate|null $existing): ?string
    {
        $key = $kind === 'invoice' ? 'due_date' : 'expiry_date';

        if (array_key_exists($key, $intent)) {
            return $intent[$key] === null ? null : $this->parseDate($intent[$key], $key, false);
        }

        if ($existing) {
            $stored = $existing->getRawOriginal($key);

            return $stored ? CarbonImmutable::parse($stored)->format('Y-m-d') : null;
        }

        [$automatic, $days] = $kind === 'invoice'
            ? ['invoice_set_due_date_automatically', 'invoice_due_date_days']
            : ['estimate_set_expiry_date_automatically', 'estimate_expiry_date_days'];

        if ($settings->get($automatic) !== 'YES' || $date === null) {
            return null;
        }

        return CarbonImmutable::parse($date)->addDays((int) ($settings->get($days) ?? 7))->format('Y-m-d');
    }

    private function parseDate(mixed $value, string $path, bool $withTime): ?string
    {
        $pattern = $withTime ? '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/' : '/^\d{4}-\d{2}-\d{2}$/';

        if (! is_string($value) || preg_match($pattern, $value) !== 1 || strtotime($value) === false) {
            $this->fail($path, $withTime ? 'Dates are written YYYY-MM-DD, optionally with HH:MM.' : 'Dates are written YYYY-MM-DD.');

            return null;
        }

        return $value;
    }

    private function today(Collection $settings): CarbonImmutable
    {
        $zone = $settings->get('time_zone');

        return CarbonImmutable::now(is_string($zone) && $zone !== '' ? $zone : config('app.timezone'));
    }

    /**
     * The number the form would show for a new document, counted for this
     * customer so a per-customer series comes out right.
     */
    private function nextNumber(string $kind, int $companyId, int $customerId): ?string
    {
        $serial = (new SerialNumberService)
            ->setCompany($companyId)
            ->setCustomer($customerId)
            ->setModel($kind === 'invoice' ? new Invoice : new Estimate);

        if ($kind === 'invoice') {
            $serial->setSequenceScope(['type' => Invoice::TYPE_INVOICE]);
        }

        return $serial->setModelObject(null)->getNextNumber();
    }

    private function fail(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }
}
