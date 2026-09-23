<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Application\Composition\SalesDocumentComposer;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/*
 * What the composer produces for a document, compared with what the invoice
 * form submitted for the same document.
 *
 * Each fixture in tests/Fixtures/document-payloads/ is a session on the real
 * form: the company's tax and discount settings, what was typed and picked
 * (`form`), and the money fields of the request the form sent (`submitted`).
 * They were recorded with tests/Fixtures/document-payloads/capture.js against
 * a local install that has the tax types, customers and item made below.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $usd = Currency::where('code', 'USD')->value('id');

    CompanySetting::setSettings(['currency' => $usd, 'tax_included' => 'YES', 'tax_included_by_default' => 'NO'], $this->companyId);

    $this->taxTypes = collect([
        ['Parity VAT', 20, 'percentage', null, false],
        ['Parity Levy', 5, 'percentage', null, true],
        ['Parity Eco', 0, 'fixed', 250, false],
        ['Parity Odd', 7.25, 'percentage', null, false],
    ])->mapWithKeys(fn (array $type) => [$type[0] => TaxType::factory()->create([
        'company_id' => $this->companyId,
        'name' => $type[0],
        'percent' => $type[1],
        'calculation_type' => $type[2],
        'fixed_amount' => $type[3],
        'compound_tax' => $type[4],
        'type' => TaxType::TYPE_GENERAL,
    ])->id]);

    $this->customers = [
        'Parity Home LLC' => Customer::factory()->create(['company_id' => $this->companyId, 'name' => 'Parity Home LLC', 'currency_id' => $usd])->id,
        'Parity Euro GmbH' => Customer::factory()->create(['company_id' => $this->companyId, 'name' => 'Parity Euro GmbH', 'currency_id' => Currency::where('code', 'EUR')->value('id')])->id,
    ];

    $widget = Item::factory()->create(['company_id' => $this->companyId, 'name' => 'Parity Widget', 'description' => 'A widget', 'price' => 12345]);
    $widget->taxes()->create([
        'tax_type_id' => $this->taxTypes['Parity VAT'],
        'name' => 'Parity VAT',
        'percent' => 20,
        'calculation_type' => 'percentage',
        'amount' => 0,
        'compound_tax' => false,
        'company_id' => $this->companyId,
    ]);
    $this->items = ['Parity Widget' => $widget->id];
});

/**
 * The intent that describes what was done on the form.
 */
function intentFromForm(array $form, array $customers, array $items, Collection $taxTypes): array
{
    $taxIds = fn (array $names) => array_map(fn (string $name) => $taxTypes[$name], $names);

    $intent = [
        'customer_id' => $customers[$form['customer']],
        'prices_include_tax' => (bool) ($form['inclusive'] ?? false),
        'lines' => array_map(fn (array $line) => array_filter([
            'item_id' => isset($line['item']) ? $items[$line['item']] : null,
            'name' => $line['name'] ?? null,
            'quantity' => $line['qty'],
            'unit_price' => $line['price'] ?? null,
            'discount' => $line['discount'] ?? null,
            'discount_type' => $line['discount_type'] ?? null,
            'tax_type_ids' => isset($line['taxes']) ? $taxIds($line['taxes']) : null,
        ], fn ($value) => $value !== null), $form['lines']),
    ];

    if (isset($form['exchange_rate'])) {
        $intent['exchange_rate'] = $form['exchange_rate'];
    }

    if (isset($form['discount'])) {
        $intent['discount'] = $form['discount'];
        $intent['discount_type'] = $form['discount_type'];
    }

    if (isset($form['taxes'])) {
        $intent['tax_type_ids'] = $taxIds($form['taxes']);
    }

    return $intent;
}

/**
 * The money fields of a submission, numbers compared as numbers.
 */
function moneyFields(array $payload): array
{
    $number = fn ($value) => is_numeric($value) ? (float) $value : $value;
    $tax = fn (array $row) => [
        'name' => $row['name'],
        'percent' => $number($row['percent']),
        'calculation_type' => $row['calculation_type'],
        'fixed_amount' => $number($row['fixed_amount']),
        'compound_tax' => (bool) $row['compound_tax'],
        'amount' => $number($row['amount']),
    ];

    $fields = [];

    foreach (['discount', 'discount_type', 'discount_val', 'tax_per_item', 'discount_per_item', 'tax_included', 'sub_total', 'tax', 'total', 'exchange_rate'] as $key) {
        $fields[$key] = $number($payload[$key] ?? null);
    }

    $fields['currency'] = Currency::find($payload['currency_id'])?->code;
    $fields['taxes'] = array_map($tax, $payload['taxes']);
    $fields['items'] = array_map(fn (array $item) => [
        'name' => $item['name'],
        'quantity' => $number($item['quantity']),
        'price' => $number($item['price']),
        'discount' => $number($item['discount']),
        'discount_type' => $item['discount_type'],
        'discount_val' => $number($item['discount_val']),
        'tax' => $number($item['tax']),
        'total' => $number($item['total']),
        'taxes' => array_map($tax, array_values(array_filter($item['taxes'] ?? [], fn (array $row) => ! empty($row['tax_type_id'] ?? $row['name'])))),
    ], $payload['items']);

    return $fields;
}

test('the composer submits what the invoice form submitted', function (string $fixture) {
    $session = json_decode(file_get_contents(base_path("tests/Fixtures/document-payloads/{$fixture}.json")), true);

    CompanySetting::setSettings($session['settings'], $this->companyId);

    $composed = app(SalesDocumentComposer::class)->invoice(
        intentFromForm($session['form'], $this->customers, $this->items, $this->taxTypes),
        $this->companyId,
        $this->user,
    );

    // The recorded submission names its currency by the id of the install it
    // was recorded on; it is compared by code.
    $submitted = $session['submitted'];
    $submitted['currency_id'] = Currency::where('code', $session['form']['customer'] === 'Parity Euro GmbH' ? 'EUR' : 'USD')->value('id');

    expect(moneyFields($composed))->toBe(moneyFields($submitted));
})->with([
    'document-taxes-fixed-discount',
    'document-taxes-inclusive-percentage-discount',
    'line-taxes-line-discounts',
    'line-taxes-document-fixed-discount-tie',
    'line-taxes-inclusive-percentage-discount',
    'foreign-customer-catalogue-item',
    'line-taxes-catalogue-item-taxes',
]);
