<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Application\RecurringInvoiceService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses()->group('money-conversion');

beforeEach(function () {
    // Legacy factories refer to id 1. PostgreSQL sequences do not roll back
    // with RefreshDatabase's transactions, unlike the rows themselves.
    if (DB::getDriverName() === 'pgsql') {
        foreach (['users', 'companies', 'currencies', 'countries'] as $table) {
            DB::table($table)->truncate();
        }
    }
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    Queue::fake();

    $user = User::where('role', 'super admin')->firstOrFail();
    $this->company = $user->companies()->firstOrFail();
    $this->eur = Currency::where('code', 'EUR')->firstOrFail();
    $sek = Currency::where('code', 'SEK')->firstOrFail();
    CompanySetting::setSettings([
        'currency' => $sek->id,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
    ], $this->company->id);
    $this->customer = Customer::factory()->create([
        'company_id' => $this->company->id,
        'currency_id' => $this->eur->id,
    ]);
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

function foreignCurrencyEstimatePayload(Customer $customer): array
{
    return [
        'estimate_date' => '2026-09-22',
        'expiry_date' => '2026-09-29',
        'estimate_number' => 'EST-798-001',
        'customer_id' => $customer->id,
        'currency_id' => $customer->currency_id,
        'exchange_rate' => '11.257191',
        'template_name' => 'estimate1',
        'discount_type' => 'fixed',
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 10660,
        'total' => 10660,
        'tax' => 0,
        'tax_included' => false,
        'taxes' => [],
        'items' => [[
            'name' => 'Consulting',
            'quantity' => 1,
            'price' => 10660,
            'total' => 10660,
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
            'taxes' => [],
        ]],
    ];
}

test('an estimate saves the reported decimal exchange rate as integer base money', function () {
    $payload = foreignCurrencyEstimatePayload($this->customer);
    $this->postJson('/api/v1/estimates', $payload)->assertCreated();

    $estimate = Estimate::where('estimate_number', $payload['estimate_number'])->firstOrFail();
    expect($estimate->exchange_rate)->toBe(11.257191)
        ->and((string) $estimate->getRawOriginal('base_total'))->toBe('120002')
        ->and((string) $estimate->getRawOriginal('base_sub_total'))->toBe('120002')
        ->and((string) $estimate->items()->firstOrFail()->getRawOriginal('base_price'))->toBe('120002');
});

test('updating an estimate recomputes integer base money at the new rate', function () {
    $payload = foreignCurrencyEstimatePayload($this->customer);
    $payload['exchange_rate'] = 1;
    $this->postJson('/api/v1/estimates', $payload)->assertCreated();
    $estimate = Estimate::where('estimate_number', $payload['estimate_number'])->firstOrFail();

    $payload['exchange_rate'] = '11.257191';
    $this->putJson('/api/v1/estimates/'.$estimate->id, $payload)->assertOk();
    expect((string) $estimate->fresh()->getRawOriginal('base_total'))->toBe('120002')
        ->and((string) $estimate->items()->firstOrFail()->getRawOriginal('base_total'))->toBe('120002');
});

function foreignCurrencyInvoicePayload(Customer $customer): array
{
    $payload = foreignCurrencyEstimatePayload($customer);
    unset($payload['estimate_date'], $payload['expiry_date'], $payload['estimate_number']);

    return array_merge($payload, [
        'invoice_date' => '2026-09-22',
        'due_date' => '2026-09-29',
        'invoice_number' => 'INV-798-001',
        'template_name' => 'invoice1',
    ]);
}

test('estimate taxes and discounts keep their own rounded base amounts', function (string $taxMode) {
    CompanySetting::setSettings(['tax_per_item' => $taxMode], $this->company->id);
    $type = TaxType::factory()->create(['company_id' => $this->company->id, 'percent' => 10]);
    $payload = foreignCurrencyEstimatePayload($this->customer);
    $payload['exchange_rate'] = '1.125';
    $payload['discount_val'] = 7;
    $payload['sub_total'] = 304;
    $payload['tax'] = 40;
    $payload['total'] = 337;
    $payload['items'] = [];
    foreach ([[101, 13], [203, 27]] as [$price, $taxAmount]) {
        $line = foreignCurrencyEstimatePayload($this->customer)['items'][0];
        $line['price'] = $line['total'] = $price;
        $tax = ['tax_type_id' => $type->id, 'name' => 'VAT', 'percent' => 10, 'amount' => $taxAmount, 'compound_tax' => false];
        if ($taxMode === 'YES') {
            $line['tax'] = $taxAmount;
            $line['taxes'] = [$tax];
        } else {
            $payload['taxes'][] = $tax;
        }
        $payload['items'][] = $line;
    }
    $this->postJson('/api/v1/estimates', $payload)->assertCreated();
    $estimate = Estimate::where('estimate_number', $payload['estimate_number'])->firstOrFail();
    expect((string) $estimate->base_sub_total)->toBe('342')
        ->and((string) $estimate->base_discount_val)->toBe('8')
        ->and((string) $estimate->base_tax)->toBe('45')
        ->and((string) $estimate->base_total)->toBe('379');
    $items = $estimate->items()->orderBy('id')->get();
    expect((string) $items[0]->base_tax)->toBe($taxMode === 'YES' ? '15' : '0')
        ->and((string) $items[1]->base_tax)->toBe($taxMode === 'YES' ? '30' : '0');
    $taxes = $taxMode === 'YES' ? $items->flatMap->taxes : $estimate->taxes;
    expect($taxes->map(fn ($tax) => (string) $tax->base_amount)->all())->toBe(['15', '30']);
})->with(['document taxes' => 'NO', 'item taxes' => 'YES']);

test('copied estimates recompute rounded line amounts', function (string $action) {
    $payload = foreignCurrencyEstimatePayload($this->customer);
    $this->postJson('/api/v1/estimates', $payload)->assertCreated();
    $estimate = Estimate::where('estimate_number', $payload['estimate_number'])->firstOrFail();
    // A copy must derive base money from the source amount and rate, even when
    // the source holds old or incomplete base amounts.
    $estimate->items()->update(['base_price' => 1, 'base_total' => 1]);
    $this->postJson('/api/v1/estimates/'.$estimate->id.'/'.$action)->assertSuccessful();
    $copy = $action === 'clone' ? Estimate::latest('id')->firstOrFail() : Invoice::latest('id')->firstOrFail();
    expect((string) $copy->base_total)->toBe('120002')
        ->and((string) $copy->items()->firstOrFail()->base_price)->toBe('120002')
        ->and((string) $copy->items()->firstOrFail()->base_total)->toBe('120002');
    if ($action === 'convert-to-invoice') {
        expect((string) $copy->base_due_amount)->toBe('120002');
    }
})->with(['clone', 'convert-to-invoice']);

test('invoices and their copies persist rounded base money', function () {
    $payload = foreignCurrencyInvoicePayload($this->customer);
    $this->postJson('/api/v1/invoices', $payload)->assertSuccessful();
    $invoice = Invoice::where('invoice_number', $payload['invoice_number'])->firstOrFail();
    expect((string) $invoice->base_total)->toBe('120002')
        ->and((string) $invoice->base_due_amount)->toBe('120002');
    $payload['exchange_rate'] = '1.125';
    $this->putJson('/api/v1/invoices/'.$invoice->id, $payload)->assertOk();
    expect((string) $invoice->fresh()->base_due_amount)->toBe('11993');
    $this->postJson('/api/v1/invoices/'.$invoice->id.'/clone')->assertSuccessful();
    $copy = Invoice::latest('id')->firstOrFail();
    expect((string) $copy->base_total)->toBe('11993')
        ->and((string) $copy->items()->firstOrFail()->base_total)->toBe('11993');
});

test('payment creation and editing round converted payment and remaining invoice balances', function () {
    $payload = foreignCurrencyInvoicePayload($this->customer);
    $this->postJson('/api/v1/invoices', $payload)->assertSuccessful();
    $invoice = Invoice::where('invoice_number', $payload['invoice_number'])->firstOrFail();
    $invoice->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);
    $payment = [
        'payment_date' => '2026-09-22', 'payment_number' => 'PAY-798-001',
        'customer_id' => $this->customer->id, 'currency_id' => $this->eur->id,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 1066]],
        'amount' => 1066, 'exchange_rate' => '11.257191',
    ];
    $this->postJson('/api/v1/payments', $payment)->assertSuccessful();
    $record = Payment::where('payment_number', $payment['payment_number'])->firstOrFail();
    expect((string) $record->base_amount)->toBe('12000')
        ->and((string) $invoice->fresh()->base_due_amount)->toBe('108001');
    $payment['amount'] = 2132;
    $payment['allocations'][0]['amount'] = 2132;
    $this->putJson('/api/v1/payments/'.$record->id, $payment)->assertSuccessful();
    expect((string) $record->fresh()->base_amount)->toBe('24000')
        ->and((string) $invoice->fresh()->base_due_amount)->toBe('96001');
});

test('expenses round converted money on create and update', function () {
    $category = ExpenseCategory::factory()->create(['company_id' => $this->company->id]);
    $payload = [
        'expense_date' => '2026-09-22', 'expense_number' => 'EXP-798-001',
        'expense_category_id' => $category->id, 'currency_id' => $this->eur->id,
        'amount' => 10660, 'exchange_rate' => '11.257191', 'notes' => 'Currency regression',
    ];
    $this->postJson('/api/v1/expenses', $payload)->assertCreated();
    $expense = Expense::where('expense_number', $payload['expense_number'])->firstOrFail();
    expect((string) $expense->base_amount)->toBe('120002');
    $payload['amount'] = 101;
    $payload['exchange_rate'] = '1.125';
    $this->putJson('/api/v1/expenses/'.$expense->id, $payload)->assertSuccessful();
    expect((string) $expense->fresh()->base_amount)->toBe('114');
});

test('recurring templates and generated invoices keep integer base amounts', function () {
    $payload = foreignCurrencyInvoicePayload($this->customer);
    unset($payload['invoice_number'], $payload['invoice_date'], $payload['due_date']);
    $payload = array_merge($payload, [
        'starts_at' => now()->subDay()->toDateString(), 'send_automatically' => false,
        'frequency' => '0 0 * * *', 'limit_by' => 'NONE', 'status' => 'ACTIVE',
    ]);
    $this->postJson('/api/v1/recurring-invoices', $payload)->assertSuccessful();
    $recurring = RecurringInvoice::latest('id')->firstOrFail();
    expect((string) $recurring->items()->firstOrFail()->base_price)->toBe('120002');
    $payload['exchange_rate'] = '1.125';
    $this->putJson('/api/v1/recurring-invoices/'.$recurring->id, $payload)->assertSuccessful();
    app(RecurringInvoiceService::class)->generateInvoice($recurring->fresh(), false);
    $invoice = Invoice::where('recurring_invoice_id', $recurring->id)->firstOrFail();
    expect((string) $invoice->base_total)->toBe('11993')
        ->and((string) $invoice->base_due_amount)->toBe('11993')
        ->and((string) $invoice->items()->firstOrFail()->base_total)->toBe('11993');
});

test('exchange rate backfill uses original discounts and taxes without converting taxes twice', function () {
    $payload = foreignCurrencyEstimatePayload($this->customer);
    $payload['exchange_rate'] = 1;
    $payload['discount_val'] = 7;
    $this->postJson('/api/v1/estimates', $payload)->assertCreated();
    $estimate = Estimate::where('estimate_number', $payload['estimate_number'])->firstOrFail();
    $invoicePayload = foreignCurrencyInvoicePayload($this->customer);
    $invoicePayload['exchange_rate'] = 1;
    $invoicePayload['discount_val'] = 7;
    $this->postJson('/api/v1/invoices', $invoicePayload)->assertSuccessful();
    $invoice = Invoice::where('invoice_number', $invoicePayload['invoice_number'])->firstOrFail();
    $type = TaxType::factory()->create(['company_id' => $this->company->id, 'percent' => 10]);
    $tax = Tax::factory()->create([
        'tax_type_id' => $type->id,
        'estimate_id' => $estimate->id, 'company_id' => $this->company->id,
        'currency_id' => $this->eur->id, 'amount' => 13, 'base_amount' => 999,
    ]);
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->company->id);
    $this->postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $this->eur->id, 'exchange_rate' => '1.125']],
    ])->assertSuccessful();
    expect((string) $estimate->fresh()->base_discount_val)->toBe('8')
        ->and((string) $invoice->fresh()->base_discount_val)->toBe('8')
        ->and((string) $tax->fresh()->base_amount)->toBe('15');
});

test('partial credits preserve allocated base amounts instead of rounding each credit again', function () {
    $payload = foreignCurrencyInvoicePayload($this->customer);
    $payload['exchange_rate'] = '1.5';
    $payload['items'][0]['price'] = 1;
    $payload['items'][0]['quantity'] = 3;
    $payload['items'][0]['total'] = $payload['sub_total'] = $payload['total'] = 3;
    $this->postJson('/api/v1/invoices', $payload)->assertSuccessful();
    $invoice = Invoice::where('invoice_number', $payload['invoice_number'])->firstOrFail();
    $invoice->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);
    $item = $invoice->items()->firstOrFail();
    expect((string) $invoice->base_total)->toBe('5');
    $baseAmounts = [];
    for ($i = 0; $i < 3; $i++) {
        $response = $this->postJson('/api/v1/invoices/'.$invoice->id.'/credit-note', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
        ])->assertCreated();
        $credit = Invoice::findOrFail($response->json('data.id'));
        $baseAmounts[] = (int) $credit->base_total;
        expect((string) $credit->items()->firstOrFail()->base_total)->toBe((string) $credit->base_total);
    }
    expect($baseAmounts)->toBe([-2, -1, -2])
        ->and(array_sum($baseAmounts))->toBe(-5)
        ->and((string) $invoice->fresh()->base_due_amount)->toBe('0');
});
