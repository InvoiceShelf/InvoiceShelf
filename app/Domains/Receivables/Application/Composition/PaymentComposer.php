<?php

namespace App\Domains\Receivables\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Money\Application\ExchangeRateLookup;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Contracts\InvoiceBalanceUpdater;
use App\Domains\Receivables\Contracts\PaymentNumberAssigner;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Sales\Models\Invoice;
use App\Support\MinorUnits;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Turns a description of a received payment into what the payment form would
 * submit: number, date, method, currency and rate, and the invoices it
 * settles.
 *
 * Invoices are reached through allocations only. An allocation that names no
 * amount takes what is still open on the invoice (its total, less what credit
 * notes and other payments already cover), up to what is left of the
 * payment. The allocation engine makes the final checks when the payment is
 * stored; the ones made here say plainly what is wrong before anything is
 * written.
 *
 * The intent, all keys optional unless noted:
 *
 * - `customer_id`, or allocations whose invoices name the customer;
 * - `amount` (major units), else the sum of what the allocations take;
 * - `date` (`Y-m-d`), `number`, `notes`, `exchange_rate`;
 * - `payment_method`: an id or a name;
 * - `custom_fields`: `[{slug or id, value}]`;
 * - `allocations`: `[{invoice_id or invoice_number, amount (major units)}]`.
 */
class PaymentComposer
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    public function __construct(
        private readonly PaymentNumberAssigner $paymentNumbers,
        private readonly ExchangeRateLookup $exchangeRates,
        private readonly InvoiceBalanceUpdater $invoiceBalances,
    ) {}

    /**
     * What the payment form would submit.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     *
     * @throws ValidationException naming every part of the intent that is wrong
     */
    public function payment(array $intent, int $companyId, ?Payment $existing = null): array
    {
        $this->errors = [];
        $settings = CompanySetting::getSettings(['currency', 'time_zone'], $companyId);

        $invoices = $this->allocatedInvoices($intent['allocations'] ?? [], $companyId);
        $customer = $this->customer($intent['customer_id'] ?? $existing?->customer_id ?? ($invoices[0] ?? null)?->customer_id, $companyId);

        $homeCurrency = (int) $settings->get('currency');
        $currencyId = (int) ($customer?->currency_id ?: $homeCurrency);
        $rate = $currencyId === $homeCurrency ? null : $this->exchangeRate($intent, $currencyId, $companyId, $existing);

        $amount = null;

        if (array_key_exists('amount', $intent) && $intent['amount'] !== null) {
            $amount = MinorUnits::fromMajor($intent['amount']);

            if ($amount === null || $amount < 1) {
                $this->fail('amount', 'The amount is a positive amount in major units with at most two decimals.');
            }
        }

        $allocations = $customer ? $this->allocations($intent['allocations'] ?? [], $invoices, $customer, $currencyId, $amount, $existing) : [];

        if ($amount === null && ! $this->hasErrors('amount')) {
            $amount = $existing && ! array_key_exists('allocations', $intent)
                ? (int) $existing->amount
                : array_sum(array_column($allocations, 'amount'));

            if ($amount < 1) {
                $this->fail('amount', 'Give the amount received, or allocations to invoices with an open balance.');
            }
        }

        $methodId = $this->paymentMethod($intent, $companyId, $existing);
        $date = $this->date($intent, $settings->get('time_zone'), $existing);
        $customFields = $this->customFields($intent, $companyId, $existing);

        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }

        $payload = [
            'payment_date' => $date,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_number' => $intent['number'] ?? $existing?->payment_number ?? $this->paymentNumbers->next(new Payment, $companyId, $customer->id, generateNumber: true)->number,
            'payment_method_id' => $methodId,
            'notes' => array_key_exists('notes', $intent) ? $intent['notes'] : $existing?->notes,
            'customFields' => $customFields,
        ];

        if ($rate !== null) {
            $payload['exchange_rate'] = $rate;
        }

        // Left out on an update that does not mention them, so the stored
        // allocations stay as they are.
        if (! $existing || array_key_exists('allocations', $intent)) {
            $payload['allocations'] = $allocations;
        }

        return $payload;
    }

    /**
     * The invoices the allocations name, by id or by number, in order. A slot
     * is null where the invoice could not be found.
     *
     * @return list<Invoice|null>
     */
    private function allocatedInvoices(mixed $allocations, int $companyId): array
    {
        if (! is_array($allocations)) {
            $this->fail('allocations', 'allocations is a list of {invoice_id or invoice_number, amount}.');

            return [];
        }

        $invoices = [];

        foreach (array_values($allocations) as $index => $allocation) {
            $query = Invoice::query()->where('company_id', $companyId);

            if (is_array($allocation) && isset($allocation['invoice_id'])) {
                $invoice = $query->find($allocation['invoice_id']);
            } elseif (is_array($allocation) && isset($allocation['invoice_number'])) {
                $invoice = $query->where('invoice_number', $allocation['invoice_number'])->first();
            } else {
                $this->fail("allocations.{$index}", 'Each allocation names an invoice by invoice_id or invoice_number.');
                $invoices[] = null;

                continue;
            }

            if (! $invoice) {
                $this->fail("allocations.{$index}", 'There is no such invoice in the company.');
            }

            $invoices[] = $invoice;
        }

        return $invoices;
    }

    /**
     * @param  list<Invoice|null>  $invoices
     * @return list<array{invoice_id: int, amount: int}>
     */
    private function allocations(mixed $given, array $invoices, Customer $customer, int $currencyId, ?int $amount, ?Payment $existing): array
    {
        if (! is_array($given)) {
            return [];
        }

        $remaining = $amount;
        $allocations = [];

        foreach (array_values($given) as $index => $allocation) {
            $invoice = $invoices[$index] ?? null;

            if (! $invoice) {
                continue;
            }

            $path = "allocations.{$index}";

            if ((int) $invoice->customer_id !== $customer->id) {
                $this->fail($path, "Invoice {$invoice->invoice_number} belongs to another customer.");

                continue;
            }

            if ((int) $invoice->currency_id !== $currencyId) {
                $this->fail($path, "Invoice {$invoice->invoice_number} is in another currency than the payment.");

                continue;
            }

            if ($invoice->type !== Invoice::TYPE_INVOICE) {
                $this->fail($path, "{$invoice->invoice_number} is a credit note; payments settle invoices.");

                continue;
            }

            if ($invoice->status === Invoice::STATUS_DRAFT) {
                $this->fail($path, "Invoice {$invoice->invoice_number} is a draft; mark it as sent before recording a payment against it.");

                continue;
            }

            $open = $this->openBalance($invoice, $existing);

            if (isset($allocation['amount'])) {
                $share = MinorUnits::fromMajor($allocation['amount']);

                if ($share === null || $share < 1) {
                    $this->fail("{$path}.amount", 'The allocated amount is a positive amount in major units.');

                    continue;
                }
            } elseif ($remaining !== null && $remaining < 1) {
                $this->fail($path, 'Nothing of the payment is left for this invoice after the allocations before it.');

                continue;
            } else {
                $share = $remaining === null ? $open : min($open, $remaining);
            }

            if ($share < 1) {
                $this->fail($path, "Invoice {$invoice->invoice_number} has nothing left to pay.");

                continue;
            }

            if ($share > $open) {
                $this->fail("{$path}.amount", "Invoice {$invoice->invoice_number} has only ".number_format($open / 100, 2, '.', '').' left to pay.');

                continue;
            }

            if ($remaining !== null) {
                $remaining -= $share;
            }

            $allocations[] = ['invoice_id' => $invoice->id, 'amount' => $share];
        }

        if ($amount !== null && $remaining !== null && $remaining < 0) {
            $this->fail('allocations', 'The allocations add up to more than the payment.');
        }

        return $allocations;
    }

    /**
     * What is still open on an invoice for this payment: its total, less what
     * credit notes cover and what other payments already settle.
     */
    private function openBalance(Invoice $invoice, ?Payment $existing): int
    {
        $settledElsewhere = (int) PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->when($existing, fn ($query) => $query->where('payment_id', '!=', $existing->id))
            ->sum('amount');

        return max(0, (int) $invoice->total - $this->invoiceBalances->creditedTotal($invoice) - $settledElsewhere);
    }

    private function customer(mixed $id, int $companyId): ?Customer
    {
        $customer = is_numeric($id)
            ? Customer::query()->where('company_id', $companyId)->find((int) $id)
            : null;

        if (! $customer) {
            $this->fail('customer_id', $id === null
                ? 'A customer is required, or allocations to their invoices.'
                : 'There is no customer with this id in the company.');
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function exchangeRate(array $intent, int $currencyId, int $companyId, ?Payment $existing): ?float
    {
        if (isset($intent['exchange_rate'])) {
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
    private function paymentMethod(array $intent, int $companyId, ?Payment $existing): ?int
    {
        if (! array_key_exists('payment_method', $intent)) {
            return $existing?->payment_method_id;
        }

        $given = $intent['payment_method'];

        if ($given === null || $given === '') {
            return null;
        }

        $methods = PaymentMethod::query()
            ->where('company_id', $companyId)
            ->where('type', PaymentMethod::TYPE_GENERAL)
            ->get(['id', 'name']);

        $method = is_numeric($given)
            ? $methods->firstWhere('id', (int) $given)
            : $methods->first(fn (PaymentMethod $method) => mb_strtolower($method->name) === mb_strtolower(trim((string) $given)));

        if (! $method) {
            $this->fail('payment_method', 'The payment method is one of: '.$methods->pluck('name')->implode(', ').'.');

            return null;
        }

        return $method->id;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function date(array $intent, mixed $zone, ?Payment $existing): ?string
    {
        if (isset($intent['date'])) {
            $date = $intent['date'];

            if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || strtotime($date) === false) {
                $this->fail('date', 'Dates are written YYYY-MM-DD.');

                return null;
            }

            return $date;
        }

        if ($existing) {
            $stored = $existing->getRawOriginal('payment_date');

            return $stored ? CarbonImmutable::parse($stored)->format('Y-m-d') : null;
        }

        return CarbonImmutable::now(is_string($zone) && $zone !== '' ? $zone : config('app.timezone'))->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return list<array<string, mixed>>
     */
    private function customFields(array $intent, int $companyId, ?Payment $existing): array
    {
        $answers = [];

        if (array_key_exists('custom_fields', $intent)) {
            foreach ((array) $intent['custom_fields'] as $index => $answer) {
                if (! is_array($answer) || (! isset($answer['slug']) && ! isset($answer['id'])) || ! array_key_exists('value', $answer)) {
                    $this->fail("custom_fields.{$index}", 'Each answer names its field by slug (or id) and has a value.');

                    continue;
                }

                $answers[] = array_intersect_key($answer, array_flip(['id', 'slug', 'value']));
            }
        } elseif ($existing) {
            $answers = $existing->fields()->get()
                ->map(fn ($answer) => ['id' => $answer->custom_field_id, 'value' => $answer->defaultAnswer])
                ->values()
                ->all();
        }

        $required = CustomField::query()
            ->where('company_id', $companyId)
            ->where('model_type', 'Payment')
            ->where('is_required', true)
            ->get(['id', 'slug', 'label']);

        foreach ($required as $field) {
            $answered = collect($answers)->contains(fn (array $answer) => ((isset($answer['id']) && (int) $answer['id'] === $field->id)
                    || (isset($answer['slug']) && $answer['slug'] === $field->slug))
                && $answer['value'] !== null && $answer['value'] !== '');

            if (! $answered) {
                $this->fail('custom_fields', "The custom field \"{$field->label}\" (slug {$field->slug}) is required.");
            }
        }

        return $answers;
    }

    private function hasErrors(string $path): bool
    {
        return isset($this->errors[$path]);
    }

    private function fail(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }
}
