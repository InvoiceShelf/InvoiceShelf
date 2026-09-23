<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Money\Models\Currency;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Sales\Application\Composition\SalesDocumentComposer;
use App\Domains\Sales\Application\SerialNumberService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\TaxType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);

    $this->usd = Currency::where('code', 'USD')->value('id');
    $this->eur = Currency::where('code', 'EUR')->value('id');

    CompanySetting::setSettings([
        'currency' => $this->usd,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'tax_included' => 'NO',
        'tax_included_by_default' => 'NO',
        'time_zone' => 'UTC',
        'invoice_use_time' => 'NO',
        'invoice_set_due_date_automatically' => 'YES',
        'invoice_due_date_days' => '14',
        'estimate_set_expiry_date_automatically' => 'YES',
        'estimate_expiry_date_days' => '30',
    ], $this->companyId);

    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd]);
    $this->vat = TaxType::factory()->create(['company_id' => $this->companyId, 'name' => 'VAT', 'percent' => 20, 'type' => TaxType::TYPE_GENERAL]);
    $this->levy = TaxType::factory()->create(['company_id' => $this->companyId, 'name' => 'Levy', 'percent' => 5, 'compound_tax' => true, 'type' => TaxType::TYPE_GENERAL]);

    $this->composer = app(SalesDocumentComposer::class);
});

function composeInvoice(array $intent, ?Invoice $invoice = null): array
{
    return test()->composer->invoice($intent, test()->companyId, test()->user, $invoice);
}

function composeErrors(Closure $compose): array
{
    try {
        $compose();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('The intent was accepted.');
}

test('an invoice is composed the way the form fills it in', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $expectedNumber = (new SerialNumberService)
        ->setCompany($this->companyId)
        ->setCustomer($this->customer->id)
        ->setModel(new Invoice)
        ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
        ->setModelObject(null)
        ->getNextNumber();

    $payload = composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'Consulting', 'quantity' => 10, 'unit_price' => '120']],
        'discount' => '5',
        'tax_type_ids' => [$this->vat->id],
        'notes' => 'Thanks',
    ]);

    expect($payload)->toMatchArray([
        'customer_id' => $this->customer->id,
        'currency_id' => $this->usd,
        'invoice_date' => '2026-09-23',
        'due_date' => '2026-10-07',
        'invoice_number' => $expectedNumber,
        'notes' => 'Thanks',
        'discount' => 500,
        'discount_type' => 'fixed',
        'discount_val' => 500,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'tax_included' => false,
        'tax_type_ids' => [$this->vat->id],
        'sub_total' => 120000,
        'tax' => 23900,
        'total' => 143400,
    ])
        ->and($payload)->not->toHaveKey('exchange_rate')
        ->and($payload['items'][0])->toMatchArray(['name' => 'Consulting', 'quantity' => 10, 'price' => 12000, 'discount_val' => 0, 'tax' => 0, 'total' => 120000])
        ->and($payload['taxes'][0])->toMatchArray(['tax_type_id' => $this->vat->id, 'name' => 'VAT', 'amount' => 23900, 'compound_tax' => false])
        ->and($payload['template_name'])->not->toBeEmpty();

    CarbonImmutable::setTestNow();
});

test('a composed invoice is accepted and stored by the invoice endpoint as composed', function () {
    $payload = composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [
            ['name' => 'Design', 'quantity' => 3, 'unit_price' => '19.99'],
            ['name' => 'Build', 'quantity' => 1.5, 'unit_price' => 80],
        ],
        'discount' => 10,
        'discount_type' => 'percentage',
        'tax_type_ids' => [$this->vat->id, $this->levy->id],
    ]);

    $id = postJson('api/v1/invoices', $payload)->assertOk()->json('data.id');
    $invoice = Invoice::with('taxes', 'items')->find($id);

    expect($invoice->sub_total)->toBe($payload['sub_total'])
        ->and($invoice->tax)->toBe($payload['tax'])
        ->and($invoice->total)->toBe($payload['total'])
        ->and($invoice->discount_val)->toBe($payload['discount_val'])
        ->and($invoice->taxes->pluck('amount')->all())->toBe(array_column($payload['taxes'], 'amount'))
        ->and($invoice->items->pluck('total')->all())->toBe(array_column($payload['items'], 'total'));
});

test('per-line taxes and discounts are worked out on each line', function () {
    CompanySetting::setSettings(['tax_per_item' => 'YES', 'discount_per_item' => 'YES'], $this->companyId);

    $payload = composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [
            ['name' => 'A', 'quantity' => 2, 'unit_price' => '50', 'discount' => 10, 'discount_type' => 'percentage', 'tax_type_ids' => [$this->vat->id]],
            ['name' => 'B', 'quantity' => 1, 'unit_price' => '30', 'discount' => '2.50', 'tax_type_ids' => [$this->vat->id, $this->levy->id]],
        ],
    ]);

    expect($payload['items'][0])->toMatchArray(['discount' => 10, 'discount_type' => 'percentage', 'discount_val' => 1000, 'total' => 9000, 'tax' => 1800])
        ->and($payload['items'][1])->toMatchArray(['discount' => 250.0, 'discount_type' => 'fixed', 'discount_val' => 250, 'total' => 2750])
        ->and(array_column($payload['items'][1]['taxes'], 'amount'))->toBe([550, 165])
        ->and($payload['taxes'])->toBe([])
        ->and($payload)->not->toHaveKey('tax_type_ids');

    $id = postJson('api/v1/invoices', $payload)->assertOk()->json('data.id');

    expect(Invoice::find($id)->total)->toBe($payload['total']);
});

test('a catalogue item fills in the line, its taxes included', function () {
    CompanySetting::setSettings(['tax_per_item' => 'YES'], $this->companyId);
    $unit = Unit::factory()->create(['company_id' => $this->companyId, 'name' => 'hour']);
    $item = Item::factory()->create(['company_id' => $this->companyId, 'name' => 'Support', 'description' => 'Hourly', 'price' => 9000, 'unit_id' => $unit->id]);
    $item->taxes()->create(['tax_type_id' => $this->vat->id, 'name' => 'VAT', 'percent' => 20, 'calculation_type' => 'percentage', 'amount' => 0, 'compound_tax' => false, 'company_id' => $this->companyId]);

    $payload = composeInvoice(['customer_id' => $this->customer->id, 'lines' => [['item_id' => $item->id, 'quantity' => 2]]]);

    expect($payload['items'][0])->toMatchArray(['item_id' => $item->id, 'name' => 'Support', 'description' => 'Hourly', 'unit_name' => 'hour', 'price' => 9000, 'total' => 18000, 'tax' => 3600]);
});

test('a customer abroad is billed in their currency at the known rate, catalogue prices converted', function () {
    $abroad = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->eur]);
    $item = Item::factory()->create(['company_id' => $this->companyId, 'price' => 10000]);
    ExchangeRateLog::query()->create(['company_id' => $this->companyId, 'base_currency_id' => $this->eur, 'currency_id' => $this->usd, 'exchange_rate' => 1.08]);

    $payload = composeInvoice(['customer_id' => $abroad->id, 'lines' => [['item_id' => $item->id]]]);

    expect($payload['currency_id'])->toBe($this->eur)
        ->and($payload['exchange_rate'])->toBe(1.08)
        ->and($payload['items'][0]['price'])->toBe(9259);

    $id = postJson('api/v1/invoices', $payload)->assertOk()->json('data.id');

    expect((float) Invoice::find($id)->exchange_rate)->toBe(1.08);
});

test('a customer abroad without a known rate needs one given', function () {
    $abroad = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->eur]);

    expect(composeErrors(fn () => composeInvoice(['customer_id' => $abroad->id, 'lines' => [['name' => 'X', 'unit_price' => '1']]])))
        ->toHaveKey('exchange_rate');

    expect(composeInvoice(['customer_id' => $abroad->id, 'exchange_rate' => '1.1', 'lines' => [['name' => 'X', 'unit_price' => '1']]])['exchange_rate'])
        ->toBe(1.1);
});

test('only the company sales taxes may be applied', function () {
    $purchase = TaxType::factory()->create(['company_id' => $this->companyId, 'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES, 'type' => TaxType::TYPE_GENERAL]);
    $module = TaxType::factory()->create(['company_id' => $this->companyId, 'type' => TaxType::TYPE_MODULE]);
    $elsewhere = TaxType::factory()->create(['company_id' => Company::factory()->create()->id, 'type' => TaxType::TYPE_GENERAL]);

    $errors = composeErrors(fn () => composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'X', 'unit_price' => '1']],
        'tax_type_ids' => [$this->vat->id, $purchase->id, $module->id, $elsewhere->id],
    ]));

    expect(array_keys($errors))->toBe(['tax_type_ids.1', 'tax_type_ids.2', 'tax_type_ids.3']);

    expect(composeErrors(fn () => composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'X', 'unit_price' => '1']],
        'tax_type_ids' => [$this->vat->id, $this->vat->id],
    ])))->toHaveKey('tax_type_ids');
});

test('taxes and discounts go where the company applies them', function () {
    $documentLevel = composeErrors(fn () => composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'X', 'unit_price' => '1', 'tax_type_ids' => [$this->vat->id], 'discount' => 1]],
    ]));

    CompanySetting::setSettings(['tax_per_item' => 'YES', 'discount_per_item' => 'YES'], $this->companyId);

    $lineLevel = composeErrors(fn () => composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'X', 'unit_price' => '1']],
        'tax_type_ids' => [$this->vat->id],
        'discount' => 5,
    ]));

    expect(array_keys($documentLevel))->toBe(['lines.0.discount', 'lines.0.tax_type_ids'])
        ->and(array_keys($lineLevel))->toBe(['discount', 'tax_type_ids']);
});

test('required custom fields must be answered, on the document and on each line', function () {
    $document = CustomField::factory()->create(['company_id' => $this->companyId, 'model_type' => 'Invoice', 'type' => 'Text', 'is_required' => true, 'label' => 'PO number']);
    $line = CustomField::factory()->create(['company_id' => $this->companyId, 'model_type' => 'Item', 'type' => 'Text', 'is_required' => true, 'label' => 'Serial']);

    $errors = composeErrors(fn () => composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'X', 'unit_price' => '1']],
    ]));

    expect(array_keys($errors))->toBe(['lines.0.custom_fields', 'custom_fields'])
        ->and($errors['custom_fields'][0])->toContain('PO number', $document->slug);

    $payload = composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'X', 'unit_price' => '1', 'custom_fields' => [['slug' => $line->slug, 'value' => 'SN-1']]]],
        'custom_fields' => [['slug' => $document->slug, 'value' => 'PO-7']],
    ]);

    expect($payload['customFields'])->toBe([['slug' => $document->slug, 'value' => 'PO-7']]);

    postJson('api/v1/invoices', $payload)->assertOk();
});

test('prices can include tax only where the company allows it', function () {
    expect(composeErrors(fn () => composeInvoice([
        'customer_id' => $this->customer->id,
        'prices_include_tax' => true,
        'lines' => [['name' => 'X', 'unit_price' => '1']],
    ])))->toHaveKey('prices_include_tax');

    CompanySetting::setSettings(['tax_included' => 'YES'], $this->companyId);

    $payload = composeInvoice([
        'customer_id' => $this->customer->id,
        'prices_include_tax' => true,
        'lines' => [['name' => 'X', 'unit_price' => '1200']],
        'tax_type_ids' => [$this->vat->id],
    ]);

    expect($payload['tax_included'])->toBeTrue()
        ->and($payload['taxes'][0]['amount'])->toBe(20000)
        ->and($payload['total'])->toBe(120000);
});

test('an update keeps what the intent leaves out, and a tax keeps the rate it was applied at', function () {
    $created = postJson('api/v1/invoices', composeInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'Retainer', 'quantity' => 1, 'unit_price' => '1000']],
        'tax_type_ids' => [$this->vat->id],
        'notes' => 'Original',
    ]))->assertOk()->json('data.id');

    $this->vat->update(['percent' => 25]);
    $invoice = Invoice::find($created);

    $payload = composeInvoice(['discount' => '100'], $invoice);

    expect($payload['invoice_number'])->toBe($invoice->invoice_number)
        ->and($payload['notes'])->toBe('Original')
        ->and($payload['items'][0])->toMatchArray(['name' => 'Retainer', 'price' => 100000])
        ->and($payload['taxes'][0])->toMatchArray(['percent' => 20.0, 'amount' => 18000])
        ->and($payload['total'])->toBe(108000);

    putJson("api/v1/invoices/{$invoice->id}", $payload)->assertOk();

    expect($invoice->fresh()->total)->toBe(108000);
});

test('an estimate takes its own number series and expiry date', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $payload = $this->composer->estimate([
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'Build', 'unit_price' => '500']],
    ], $this->companyId, $this->user);

    expect($payload)->toHaveKeys(['estimate_number', 'estimate_date', 'expiry_date'])
        ->and($payload['estimate_date'])->toBe('2026-09-23')
        ->and($payload['expiry_date'])->toBe('2026-10-23')
        ->and($payload)->not->toHaveKey('invoice_number');

    $id = postJson('api/v1/estimates', $payload)->assertSuccessful()->json('data.id');

    expect(Estimate::find($id)->total)->toBe(50000);

    CarbonImmutable::setTestNow();
});

test('every mistake in an intent is reported at once', function () {
    $errors = composeErrors(fn () => composeInvoice([
        'customer_id' => 999999,
        'date' => '23/09/2026',
        'template' => 'nope',
        'lines' => [['quantity' => 'two', 'unit_price' => '1.234'], ['name' => 'Y', 'item_id' => 999999, 'unit_price' => '1']],
    ]));

    expect(array_keys($errors))->toEqualCanonicalizing([
        'customer_id', 'date', 'template', 'lines.0.name', 'lines.0.quantity', 'lines.0.unit_price', 'lines.1.item_id',
    ]);
});

test('amounts in major units become minor units', function (mixed $major, ?int $minor) {
    expect(SalesDocumentComposer::toMinor($major))->toBe($minor);
})->with([
    ['19.99', 1999],
    ['120', 12000],
    [12.5, 1250],
    [7, 700],
    ['-3.5', -350],
    ['0.05', 5],
    ['1.234', null],
    ['1,50', null],
    ['abc', null],
    [null, null],
]);
