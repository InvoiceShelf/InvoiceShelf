<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Reporting\Queries\PurchasesQuery;
use App\Domains\Reporting\Queries\TaxSummaryQuery;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    purchaseFixtures($this);
    $this->company = Company::findOrFail($this->companyId);
    CompanySetting::setSettings(['language' => 'en', 'carbon_date_format' => 'Y-m-d', 'time_zone' => 'UTC'], $this->companyId);
    config(['pdf.driver' => 'dompdf']);
});
afterEach(fn () => CarbonImmutable::setTestNow());

test('purchase PDF and JSON share supplier filtered cash costs taxes and current balances', function () {
    CarbonImmutable::setTestNow('2026-10-05');
    $tax = TaxType::factory()->create(['company_id' => $this->companyId, 'name' => 'Purchase VAT', 'type' => 'GENERAL', 'transaction_type' => 'purchases', 'percent' => 20, 'calculation_type' => 'percentage', 'compound_tax' => false]);
    $payload = purchaseBillPayload($this, 10000);
    $payload['items'][0]['tax_type_ids'] = [$tax->id];
    $documents = app(PurchaseDocumentService::class);
    $settlements = app(SupplierSettlementService::class);
    $bill = $documents->saveBill(null, $this->companyId, $this->user->id, $payload);
    $other = Supplier::create(['company_id' => $this->companyId, 'name' => 'Excluded supplier', 'currency_id' => $this->currencyId]);
    $documents->saveBill(null, $this->companyId, $this->user->id, [...$payload, 'supplier_id' => $other->id]);
    $settlements->recordPayment($this->companyId, $this->user->id, ['supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'amount' => 5000, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill->id, 'amount' => 4000]]]);
    $credit = $documents->createCredit($this->companyId, $this->user->id, [...$payload, 'source_bill_id' => $bill->id, 'items' => [[...$payload['items'][0], 'source_bill_item_id' => $bill->items[0]->id, 'quantity' => 0.1]]]);
    $settlements->recordRefund($this->companyId, $this->user->id, ['supplier_credit_id' => $credit->id, 'amount' => 200, 'exchange_rate' => 1, 'payment_date' => '2026-09-03']);
    $expense = Expense::factory()->create(['company_id' => $this->companyId, 'supplier_id' => $this->supplier->id, 'expense_category_id' => $this->category->id, 'expense_date' => '2026-09-04', 'amount' => 600, 'base_amount' => 600]);
    Tax::factory()->create(['company_id' => $this->companyId, 'expense_id' => $expense->id, 'name' => 'Purchase VAT', 'tax_type_id' => $tax->id, 'percent' => 20, 'amount' => 100, 'base_amount' => 100]);
    $params = 'from_date=2026-09-01&to_date=2026-09-30&supplier_id='.$this->supplier->id;
    $api = $this->getJson('/api/v1/reports/purchases?'.$params)->assertOk()->json('data');
    expect($api['purchases'])->toBe(['gross' => 11400, 'tax' => 1900, 'net' => 9500])
        ->and($api['cash']['net_cash_out'])->toBe(5400)->and($api['payables']['outstanding'])->toBe(8000)
        ->and($api['payables']['available_advances'])->toBe(1000)->and($api['payables']['available_credits'])->toBe(1000)
        ->and($api['payables']['as_of_date'])->toBe('2026-10-05');
    $pdf = $this->get('/reports/purchases/'.$this->company->unique_hash.'?'.$params.'&preview=true')->assertOk()
        ->assertSee($this->supplier->name)->assertDontSee($other->name)->assertSee('2026-10-05');
    foreach (['cash', 'purchases', 'payables', 'taxes', 'categories'] as $key) {
        expect($pdf->viewData('report')[$key])->toEqual($api[$key]);
    }
    $taxReport = app(TaxSummaryQuery::class)->report($this->companyId, '2026-09-01', '2026-09-30');
    expect((int) $taxReport['purchases']->sum('total_tax_amount'))->toBe(3900);
});

test('payables retain today in the company timezone regardless of report window', function () {
    CarbonImmutable::setTestNow('2026-09-30 12:00:00 UTC');
    CompanySetting::setSettings(['time_zone' => 'Pacific/Kiritimati'], $this->companyId);
    app(PurchaseDocumentService::class)->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 10000));
    $query = app(PurchasesQuery::class);
    $recent = $query->report($this->companyId, '2026-09-01', '2026-09-30');
    $past = $query->report($this->companyId, '2020-01-01', '2020-01-31');
    expect($recent['payables'])->toBe($past['payables'])->and($past['payables']['as_of_date'])->toBe('2026-10-01')
        ->and($past['payables']['overdue'])->toBe(10000)->and($past['purchases']['gross'])->toBe(0);
});

test('document tax basis includes unpaid partial paid and credit sales and excludes drafts and outside dates', function () {
    $type = TaxType::factory()->create(['company_id' => $this->companyId, 'name' => 'Original tax name']);
    $rows = [
        ['2026-09-01', 'SENT', 'UNPAID', 'INVOICE', 100],
        ['2026-09-15', 'VIEWED', 'PARTIALLY_PAID', 'INVOICE', 200],
        ['2026-09-30', 'COMPLETED', 'PAID', 'INVOICE', 300],
        ['2026-09-30', 'COMPLETED', 'PAID', 'CREDIT_NOTE', -50],
        ['2026-09-15', 'DRAFT', 'UNPAID', 'INVOICE', 900],
        ['2026-10-01', 'SENT', 'PAID', 'INVOICE', 800],
        ['2026-09-30 23:59:59', 'SENT', 'UNPAID', 'INVOICE', 25],
    ];
    foreach ($rows as $index => [$date, $status, $paid, $documentType, $amount]) {
        $invoice = Invoice::factory()->create(['company_id' => $this->companyId, 'invoice_date' => $date, 'status' => $status, 'paid_status' => $paid, 'type' => $documentType]);
        $owner = $index === 1
            ? ['invoice_item_id' => InvoiceItem::factory()->create(['company_id' => $this->companyId, 'invoice_id' => $invoice->id])->id]
            : ['invoice_id' => $invoice->id];
        Tax::factory()->create([...$owner, 'company_id' => $this->companyId, 'tax_type_id' => $type->id, 'name' => $type->name, 'percent' => 20, 'amount' => $amount, 'base_amount' => $amount]);
    }
    $type->update(['name' => 'Changed definition']);
    $report = $this->get('/reports/tax-summary/'.$this->company->unique_hash.'?from_date=2026-09-01&to_date=2026-09-30&preview=true')->assertOk()
        ->assertViewHas('totalTaxAmount', 575)->assertSee('Original tax name')->assertDontSee('Changed definition')->assertSee(__('pdf_tax_document_basis_note'));
    expect($report->viewData('taxTypes'))->toHaveCount(1);
});

test('purchase tax reporting excludes drafts and voids while credits retain their signs', function () {
    $tax = TaxType::factory()->create(['company_id' => $this->companyId, 'type' => 'GENERAL', 'transaction_type' => 'purchases', 'percent' => 20, 'calculation_type' => 'percentage', 'compound_tax' => false]);
    $payload = purchaseBillPayload($this, 10000);
    $payload['items'][0]['tax_type_ids'] = [$tax->id];
    $service = app(PurchaseDocumentService::class);
    $service->saveBill(null, $this->companyId, $this->user->id, [...$payload, 'status' => 'DRAFT']);
    $void = $service->saveBill(null, $this->companyId, $this->user->id, $payload);
    $service->act($void, 'void', 'Duplicate');
    $service->saveBill(null, $this->companyId, $this->user->id, [...$payload, 'document_date' => '2026-10-01', 'due_date' => '2026-10-30']);
    $service->saveBill(null, $this->companyId, $this->user->id, $payload);
    $creditPayload = $payload;
    $creditPayload['items'][0]['price'] = 1000;
    $service->createCredit($this->companyId, $this->user->id, $creditPayload);
    expect((int) app(TaxSummaryQuery::class)->report($this->companyId, '2026-09-01', '2026-09-30')['purchases']->sum('total_tax_amount'))->toBe(1800);
});

test('purchase PDF checks report permission hash membership and supplier company without trusting company headers', function () {
    $foreign = Company::factory()->create();
    $supplier = Supplier::create(['company_id' => $foreign->id, 'name' => 'Private supplier', 'currency_id' => $this->currencyId]);
    $user = User::factory()->create(['role' => 'customer']);
    $user->companies()->attach($this->companyId);
    BouncerFacade::scope()->to($this->companyId);
    BouncerFacade::allow($user)->to('view-financial-reports', null);
    BouncerFacade::refresh();
    Sanctum::actingAs($user);
    $params = '?from_date=2026-09-01&to_date=2026-09-30&preview=true';
    $this->get('/reports/purchases/'.$foreign->unique_hash.$params)->assertForbidden();
    $this->getJson('/reports/purchases/'.$this->company->unique_hash.$params.'&supplier_id='.$supplier->id)->assertUnprocessable();
    $this->get('/reports/purchases/'.$this->company->unique_hash.$params)->assertOk();
    BouncerFacade::disallow($user)->to('view-financial-reports', null);
    BouncerFacade::refresh();
    $this->get('/reports/purchases/'.$this->company->unique_hash.$params)->assertForbidden();
});

test('dashboard exposes current payables only with bill view permission', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $user->companies()->attach($this->companyId);
    BouncerFacade::scope()->to($this->companyId);
    BouncerFacade::allow($user)->to('dashboard', null);
    BouncerFacade::refresh();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('payables', null);
    BouncerFacade::allow($user)->to('view-bill', Bill::class);
    BouncerFacade::refresh();
    $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure(['payables' => ['as_of_date', 'outstanding', 'overdue', 'available_advances', 'available_credits']]);
});

test('a bill without a due date is not counted as overdue', function () {
    $bill = app(PurchaseDocumentService::class)->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 10000));
    $bill->forceFill(['due_date' => null])->saveQuietly();

    $payables = app(PurchasesQuery::class)->report($this->companyId, '2026-09-01', '2026-09-30')['payables'];

    expect($payables['overdue'])->toBe(0)
        ->and($payables['due_later'])->toBe(10000);
});

test('the tax report asks for a complete period', function () {
    $url = '/reports/tax-summary/'.$this->company->unique_hash;

    $this->getJson($url.'?to_date=2026-09-30&preview=true')->assertUnprocessable()->assertJsonValidationErrors('from_date');
    $this->getJson($url.'?from_date=2026-09-30&to_date=2026-09-01&preview=true')->assertUnprocessable()->assertJsonValidationErrors('to_date');
});
