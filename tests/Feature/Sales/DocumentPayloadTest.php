<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Http\Requests\PaymentRequest;
use App\Domains\Sales\Http\Requests\EstimatesRequest;
use App\Domains\Sales\Http\Requests\InvoicesRequest;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Artisan;

/*
 * Characterization of the attributes the three document write requests hand
 * to the services. The payload builders are being moved out of the requests;
 * these pin what they produce today, key for key and type for type.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;

    $this->usd = Currency::where('code', 'USD')->value('id');
    $this->eur = Currency::where('code', 'EUR')->value('id');

    CompanySetting::setSettings([
        'currency' => $this->usd,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
    ], $this->companyId);

    $this->home = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd]);
    $this->abroad = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->eur]);
});

/**
 * Build a write request in-process the way the router would hand it over.
 *
 * @template T of FormRequest
 *
 * @param  class-string<T>  $class
 * @return T
 */
function documentRequest(string $class, array $input, int $companyId, User $user, string $method = 'POST'): FormRequest
{
    $request = $class::create('/api/v1/documents', $method, $input);
    $request->headers->set('company', (string) $companyId);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    return $request;
}

function sortedKeys(array $payload): array
{
    ksort($payload);

    return $payload;
}

function invoiceInput(array $overrides = []): array
{
    return array_merge([
        'invoice_date' => '2026-09-01',
        'due_date' => '2026-09-15',
        'invoice_number' => 'INV-000900',
        'reference_number' => 'PO-7',
        'template_name' => 'invoice1',
        'notes' => 'Thanks',
        'discount' => 500,
        'discount_type' => 'fixed',
        'discount_val' => 500,
        'tax_included' => false,
        // Client totals are ignored and recomputed.
        'sub_total' => 1,
        'total' => 1,
        'tax' => 1,
        'items' => [
            ['name' => 'Consulting', 'quantity' => 10, 'price' => 12000, 'discount' => 0, 'discount_val' => 0, 'discount_type' => 'fixed', 'tax' => 0, 'total' => 120000],
        ],
        'taxes' => [
            ['tax_type_id' => 1, 'name' => 'VAT', 'percent' => 20, 'amount' => 23900, 'compound_tax' => false, 'calculation_type' => 'percentage'],
        ],
        'customFields' => [],
    ], $overrides);
}

test('an invoice payload keeps the input, recomputes the sums and pins what the client may not choose', function () {
    $input = invoiceInput([
        'customer_id' => $this->home->id,
        'currency_id' => $this->usd,
        'exchange_rate' => 1.5,
        // None of these may be chosen by the client.
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'related_invoice_id' => 99,
        'credit_reason' => 'x',
        'status' => 'COMPLETED',
        'paid_status' => 'PAID',
        'due_amount' => 1,
        'creator_id' => 99,
        'company_id' => 99,
        'tax_per_item' => 'YES',
        'discount_per_item' => 'YES',
        'base_total' => 1,
    ]);

    $payload = documentRequest(InvoicesRequest::class, $input, $this->companyId, $this->user)->getInvoicePayload();

    expect(sortedKeys($payload))->toBe(sortedKeys([
        'invoice_date' => '2026-09-01',
        'due_date' => '2026-09-15',
        'invoice_number' => 'INV-000900',
        'reference_number' => 'PO-7',
        'template_name' => 'invoice1',
        'notes' => 'Thanks',
        'discount' => 500,
        'discount_type' => 'fixed',
        'discount_val' => 500,
        'tax_included' => false,
        'customer_id' => $this->home->id,
        'currency_id' => $this->usd,
        'creator_id' => $this->user->id,
        'type' => Invoice::TYPE_INVOICE,
        'related_invoice_id' => null,
        'credit_reason' => null,
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'company_id' => (string) $this->companyId,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'sub_total' => 120000,
        'total' => 143400,
        'tax' => 23900,
        'due_amount' => 143400,
        'sent' => false,
        'viewed' => false,
        'exchange_rate' => 1,
        'base_total' => 143400,
        'base_discount_val' => 500,
        'base_sub_total' => 120000,
        'base_tax' => 23900,
        'base_due_amount' => 143400,
    ]));
});

test('an invoice sent on save starts out sent', function () {
    $input = invoiceInput(['customer_id' => $this->home->id, 'currency_id' => $this->usd, 'invoiceSend' => true, 'subject' => 'Hi', 'body' => 'Body']);

    $payload = documentRequest(InvoicesRequest::class, $input, $this->companyId, $this->user)->getInvoicePayload();

    expect($payload['status'])->toBe(Invoice::STATUS_SENT)
        ->and($payload['invoiceSend'])->toBeTrue()
        ->and($payload['subject'])->toBe('Hi')
        ->and($payload['body'])->toBe('Body');
});

test('a foreign invoice takes the submitted rate and is denominated in the customer currency', function () {
    $input = invoiceInput(['customer_id' => $this->abroad->id, 'currency_id' => $this->eur, 'exchange_rate' => 1.1]);

    $payload = documentRequest(InvoicesRequest::class, $input, $this->companyId, $this->user)->getInvoicePayload();

    expect($payload['exchange_rate'])->toBe(1.1)
        ->and($payload['currency_id'])->toBe($this->eur)
        ->and($payload['base_total'])->toBe(143400 * 1.1)
        ->and($payload['base_sub_total'])->toBe(120000 * 1.1)
        ->and($payload['base_tax'])->toBe(23900 * 1.1)
        ->and($payload['base_discount_val'])->toBe(500 * 1.1)
        ->and($payload['base_due_amount'])->toBe(143400 * 1.1);
});

test('the rate follows the submitted currency, the stored currency follows the customer', function () {
    // The SPA always sends the customer's currency. When it does not, the
    // rate decision reads the submitted one and the stored currency still
    // comes from the customer.
    $input = invoiceInput(['customer_id' => $this->abroad->id, 'currency_id' => $this->usd, 'exchange_rate' => 1.1]);

    $payload = documentRequest(InvoicesRequest::class, $input, $this->companyId, $this->user)->getInvoicePayload();

    expect($payload['exchange_rate'])->toBe(1)
        ->and($payload['currency_id'])->toBe($this->eur);
});

test('per line taxes and discounts follow the company settings', function () {
    CompanySetting::setSettings(['tax_per_item' => 'YES', 'discount_per_item' => 'YES'], $this->companyId);

    $input = invoiceInput([
        'customer_id' => $this->home->id,
        'currency_id' => $this->usd,
        'discount' => 0,
        'discount_val' => 0,
        'items' => [
            ['name' => 'A', 'quantity' => 2, 'price' => 5000, 'discount' => 10, 'discount_type' => 'percentage', 'discount_val' => 1000, 'tax' => 1800, 'total' => 9000, 'taxes' => [
                ['tax_type_id' => 1, 'amount' => 1800, 'compound_tax' => false],
                ['tax_type_id' => 0, 'amount' => 0],
            ]],
        ],
        'taxes' => [],
    ]);

    $payload = documentRequest(InvoicesRequest::class, $input, $this->companyId, $this->user)->getInvoicePayload();

    expect($payload['tax_per_item'])->toBe('YES')
        ->and($payload['discount_per_item'])->toBe('YES')
        ->and($payload['sub_total'])->toBe(9000)
        ->and($payload['tax'])->toBe(1800)
        ->and($payload['total'])->toBe(10800);
});

test('an estimate payload keeps the input and recomputes the sums', function () {
    $input = [
        'estimate_date' => '2026-09-01',
        'expiry_date' => '2026-09-30',
        'estimate_number' => 'EST-000900',
        'reference_number' => null,
        'template_name' => 'estimate1',
        'notes' => null,
        'discount' => 10,
        'discount_type' => 'percentage',
        'discount_val' => 12000,
        'tax_included' => true,
        'customer_id' => $this->abroad->id,
        'currency_id' => $this->eur,
        'exchange_rate' => 2,
        'sub_total' => 1,
        'total' => 1,
        'tax' => 1,
        'status' => 'ACCEPTED',
        'creator_id' => 99,
        'company_id' => 99,
        'items' => [
            ['name' => 'Build', 'quantity' => 1, 'price' => 120000, 'discount' => 0, 'discount_val' => 0, 'discount_type' => 'fixed', 'tax' => 0, 'total' => 120000],
        ],
        'taxes' => [
            ['tax_type_id' => 1, 'amount' => 18000, 'compound_tax' => false],
            ['tax_type_id' => 2, 'amount' => 5400, 'compound_tax' => true],
        ],
        'customFields' => [],
    ];

    $payload = documentRequest(EstimatesRequest::class, $input, $this->companyId, $this->user)->getEstimatePayload();

    expect(sortedKeys($payload))->toBe(sortedKeys([
        'estimate_date' => '2026-09-01',
        'expiry_date' => '2026-09-30',
        'estimate_number' => 'EST-000900',
        'reference_number' => null,
        'template_name' => 'estimate1',
        'notes' => null,
        'discount' => 10,
        'discount_type' => 'percentage',
        'discount_val' => 12000,
        'tax_included' => true,
        'customer_id' => $this->abroad->id,
        'currency_id' => $this->eur,
        'creator_id' => $this->user->id,
        'status' => 'DRAFT',
        'company_id' => (string) $this->companyId,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'sub_total' => 120000,
        // Inclusive: only the compound tax sits on top of the discounted subtotal.
        'total' => 113400,
        'tax' => 23400,
        'exchange_rate' => 2,
        'base_discount_val' => 24000,
        'base_sub_total' => 240000,
        'base_total' => 226800,
        'base_tax' => 46800,
    ]));
});

test('an estimate sent on save starts out sent', function () {
    $input = [
        'estimate_date' => '2026-09-01',
        'estimate_number' => 'EST-000901',
        'template_name' => 'estimate1',
        'discount' => 0,
        'discount_val' => 0,
        'customer_id' => $this->home->id,
        'currency_id' => $this->usd,
        'items' => [['name' => 'Build', 'quantity' => 1, 'price' => 100, 'discount_val' => 0, 'tax' => 0, 'total' => 100]],
        'estimateSend' => true,
    ];

    $payload = documentRequest(EstimatesRequest::class, $input, $this->companyId, $this->user)->getEstimatePayload();

    expect($payload['status'])->toBe('SENT')
        ->and($payload['exchange_rate'])->toBe(1)
        ->and($payload['total'])->toBe(100);
});

test('a payment payload is built from the validated input', function () {
    $input = [
        'payment_date' => '2026-09-02',
        'customer_id' => $this->abroad->id,
        'exchange_rate' => 1.25,
        'amount' => 10001,
        'payment_number' => 'PAY-000900',
        'payment_method_id' => null,
        'notes' => 'Wire',
        'allocations' => [],
        'customFields' => [],
        // Not in the rules, so never stored.
        'creator_id' => 99,
        'base_amount' => 1,
    ];

    $request = documentRequest(PaymentRequest::class, $input, $this->companyId, $this->user);
    $request->validateResolved();

    expect(sortedKeys($request->getPaymentPayload()))->toBe(sortedKeys([
        'payment_date' => '2026-09-02',
        'customer_id' => $this->abroad->id,
        'exchange_rate' => 1.25,
        'amount' => 10001,
        'payment_number' => 'PAY-000900',
        'payment_method_id' => null,
        'notes' => 'Wire',
        'creator_id' => $this->user->id,
        'company_id' => (string) $this->companyId,
        'base_amount' => 12501,
        'currency_id' => $this->eur,
    ]));
});

test('a payment at home is at par whatever rate is sent', function () {
    $input = [
        'payment_date' => '2026-09-02',
        'customer_id' => $this->home->id,
        'exchange_rate' => 3,
        'amount' => 5000,
        'payment_number' => 'PAY-000901',
    ];

    $request = documentRequest(PaymentRequest::class, $input, $this->companyId, $this->user);
    $request->validateResolved();
    $payload = $request->getPaymentPayload();

    expect($payload['exchange_rate'])->toBe(1)
        ->and($payload['base_amount'])->toBe(5000)
        ->and($payload['currency_id'])->toBe($this->usd);
});
