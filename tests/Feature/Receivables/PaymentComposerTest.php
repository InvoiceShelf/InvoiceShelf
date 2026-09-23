<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Application\Composition\PaymentComposer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Sales\Models\Invoice;
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
    CompanySetting::setSettings(['currency' => $this->usd, 'time_zone' => 'UTC'], $this->companyId);

    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd]);
    $this->invoice = openInvoice($this->customer, 10000);

    $this->composer = app(PaymentComposer::class);
});

function openInvoice(Customer $customer, int $total, array $attributes = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'currency_id' => $customer->currency_id,
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => $total,
        'due_amount' => $total,
        'base_total' => $total,
        'base_due_amount' => $total,
        'exchange_rate' => 1,
    ], $attributes));
}

function composePayment(array $intent, ?Payment $payment = null): array
{
    return test()->composer->payment($intent, test()->companyId, $payment);
}

function paymentErrors(array $intent): array
{
    try {
        composePayment($intent);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('The intent was accepted.');
}

test('a payment for an invoice takes what is open on it and settles it', function () {
    $payload = composePayment(['allocations' => [['invoice_number' => $this->invoice->invoice_number]], 'payment_method' => 'bank transfer']);

    expect($payload)->toMatchArray([
        'customer_id' => $this->customer->id,
        'amount' => 10000,
        'payment_date' => now('UTC')->format('Y-m-d'),
        'payment_method_id' => PaymentMethod::where('company_id', $this->companyId)->where('name', 'Bank Transfer')->value('id'),
        'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => 10000]],
    ])
        ->and($payload['payment_number'])->not->toBeEmpty()
        ->and($payload)->not->toHaveKey('invoice_id')
        ->and($payload)->not->toHaveKey('exchange_rate');

    postJson('api/v1/payments', $payload)->assertSuccessful();

    expect($this->invoice->fresh())
        ->paid_status->toBe(Invoice::STATUS_PAID)
        ->due_amount->toBe(0);
});

test('a smaller payment is allocated in full and leaves the rest open', function () {
    $payload = composePayment(['customer_id' => $this->customer->id, 'amount' => '60', 'allocations' => [['invoice_id' => $this->invoice->id]]]);

    expect($payload['allocations'])->toBe([['invoice_id' => $this->invoice->id, 'amount' => 6000]]);

    postJson('api/v1/payments', $payload)->assertSuccessful();

    expect($this->invoice->fresh())
        ->paid_status->toBe(Invoice::STATUS_PARTIALLY_PAID)
        ->due_amount->toBe(4000);
});

test('a larger payment settles the invoices in order and keeps the rest as credit', function () {
    $second = openInvoice($this->customer, 3000);

    $payload = composePayment(['amount' => '120', 'allocations' => [['invoice_id' => $this->invoice->id], ['invoice_id' => $second->id]]]);

    expect($payload['amount'])->toBe(12000)
        ->and($payload['allocations'])->toBe([
            ['invoice_id' => $this->invoice->id, 'amount' => 10000],
            ['invoice_id' => $second->id, 'amount' => 2000],
        ]);

    postJson('api/v1/payments', $payload)->assertSuccessful();
});

test('what other payments already settled is not offered again', function () {
    postJson('api/v1/payments', composePayment(['amount' => '25', 'allocations' => [['invoice_id' => $this->invoice->id]]]))->assertSuccessful();

    expect(composePayment(['allocations' => [['invoice_id' => $this->invoice->id]]])['amount'])->toBe(7500);
});

test('an invoice that cannot take the payment is named and explained', function () {
    $other = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd]);
    $draft = openInvoice($this->customer, 5000, ['status' => Invoice::STATUS_DRAFT]);
    $creditNote = openInvoice($this->customer, 5000, ['type' => Invoice::TYPE_CREDIT_NOTE]);
    $theirs = openInvoice($other, 5000);
    $settled = openInvoice($this->customer, 0);

    $errors = paymentErrors(['customer_id' => $this->customer->id, 'allocations' => [
        ['invoice_id' => $draft->id],
        ['invoice_id' => $creditNote->id],
        ['invoice_id' => $theirs->id],
        ['invoice_id' => $settled->id],
        ['invoice_id' => 999999],
        ['invoice_id' => $this->invoice->id, 'amount' => '101'],
    ]]);

    expect($errors['allocations.0'][0])->toContain('draft')
        ->and($errors['allocations.1'][0])->toContain('credit note')
        ->and($errors['allocations.2'][0])->toContain('another customer')
        ->and($errors['allocations.3'][0])->toContain('nothing left')
        ->and($errors['allocations.4'][0])->toContain('no such invoice')
        ->and($errors['allocations.5.amount'][0])->toContain('100.00');
});

test('allocations cannot add up to more than the payment', function () {
    $errors = paymentErrors(['amount' => '50', 'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '80']]]);

    expect($errors)->toHaveKey('allocations');
});

test('a payment needs a customer, a known method and an amount', function () {
    expect(paymentErrors([]))->toHaveKeys(['customer_id'])
        ->and(paymentErrors(['customer_id' => $this->customer->id]))->toHaveKey('amount')
        ->and(paymentErrors(['customer_id' => $this->customer->id, 'amount' => '5', 'payment_method' => 'Barter'])['payment_method'][0])
        ->toContain('Bank Transfer');
});

test('a customer abroad pays at the known rate or a given one', function () {
    $eur = Currency::where('code', 'EUR')->value('id');
    $abroad = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $eur]);

    expect(paymentErrors(['customer_id' => $abroad->id, 'amount' => '10']))->toHaveKey('exchange_rate')
        ->and(composePayment(['customer_id' => $abroad->id, 'amount' => '10', 'exchange_rate' => 1.1])['exchange_rate'])->toBe(1.1);
});

test('an update that leaves allocations out keeps the stored ones', function () {
    $id = postJson('api/v1/payments', composePayment(['allocations' => [['invoice_id' => $this->invoice->id]]]))->assertSuccessful()->json('data.id');
    $payment = Payment::find($id);

    $payload = composePayment(['notes' => 'Wire received'], $payment);

    expect($payload)->not->toHaveKey('allocations')
        ->and($payload['amount'])->toBe(10000)
        ->and($payload['payment_number'])->toBe($payment->payment_number);

    putJson("api/v1/payments/{$payment->id}", $payload)->assertOk();

    expect($payment->fresh()->notes)->toBe('Wire received')
        ->and($payment->allocations()->count())->toBe(1);
});
