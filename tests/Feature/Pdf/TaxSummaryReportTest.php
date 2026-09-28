<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $user = User::findOrFail(1);
    $this->company = $user->companies()->firstOrFail();

    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

function taxSummaryPreview(string $companyHash): TestResponse
{
    return get("/reports/tax-summary/{$companyHash}?from_date=2026-01-01&to_date=2026-01-31&preview=true");
}

function reportTax(TaxType $taxType, int $companyId, array $attributes = []): Tax
{
    return Tax::factory()->create(array_merge([
        'tax_type_id' => $taxType->id,
        'company_id' => $companyId,
        'base_amount' => 0,
    ], $attributes));
}

function reportInvoice(int $companyId, string $date, string $paidStatus): Invoice
{
    return Invoice::factory()->create([
        'company_id' => $companyId,
        'invoice_date' => $date,
        'paid_status' => $paidStatus,
    ]);
}

function reportExpense(int $companyId, string $date): Expense
{
    return Expense::factory()->create([
        'company_id' => $companyId,
        'expense_date' => $date,
    ]);
}

test('groups paid sales taxes and dated expense taxes separately for a company', function () {
    $outputTaxType = TaxType::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Output VAT',
    ]);
    $inputTaxType = TaxType::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Input VAT',
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    $paidInvoice = reportInvoice($this->company->id, '2026-01-15', Invoice::STATUS_PAID);
    reportTax($outputTaxType, $this->company->id, [
        'invoice_id' => $paidInvoice->id,
        'base_amount' => 300,
    ]);

    $paidInvoiceItem = InvoiceItem::factory()->create([
        'company_id' => $this->company->id,
        'invoice_id' => $paidInvoice->id,
    ]);
    reportTax($outputTaxType, $this->company->id, [
        'invoice_item_id' => $paidInvoiceItem->id,
        'base_amount' => 200,
    ]);

    $unpaidInvoice = reportInvoice($this->company->id, '2026-01-15', Invoice::STATUS_UNPAID);
    reportTax($outputTaxType, $this->company->id, [
        'invoice_id' => $unpaidInvoice->id,
        'base_amount' => 500,
    ]);
    $outOfRangeInvoice = reportInvoice($this->company->id, '2026-02-01', Invoice::STATUS_PAID);
    reportTax($outputTaxType, $this->company->id, [
        'invoice_id' => $outOfRangeInvoice->id,
        'base_amount' => 600,
    ]);

    $expense = reportExpense($this->company->id, '2026-01-20');
    reportTax($inputTaxType, $this->company->id, ['expense_id' => $expense->id, 'base_amount' => 150]);
    reportTax($inputTaxType, $this->company->id, ['expense_id' => $expense->id, 'base_amount' => 50]);
    $outOfRangeExpense = reportExpense($this->company->id, '2026-02-01');
    reportTax($inputTaxType, $this->company->id, ['expense_id' => $outOfRangeExpense->id, 'base_amount' => 125]);

    $otherCompany = Company::factory()->create();
    $otherTaxType = TaxType::factory()->create(['company_id' => $otherCompany->id]);
    $otherInvoice = reportInvoice($otherCompany->id, '2026-01-15', Invoice::STATUS_PAID);
    $otherInvoiceItem = InvoiceItem::factory()->create([
        'company_id' => $otherCompany->id,
        'invoice_id' => $otherInvoice->id,
    ]);
    reportTax($otherTaxType, $otherCompany->id, [
        'invoice_item_id' => $otherInvoiceItem->id,
        'base_amount' => 999,
    ]);

    $response = taxSummaryPreview($this->company->unique_hash);

    $response->assertOk()
        ->assertViewHas('taxTypes')
        ->assertViewHas('totalTaxAmount', 500)
        ->assertViewHas('expenseTaxTypes')
        ->assertViewHas('totalExpenseTaxAmount', 200)
        ->assertViewHas('netTaxAmount', 300);

    expect($response->viewData('taxTypes')->mapWithKeys(
        fn (Tax $tax) => [$tax->tax_type_id => (int) $tax->total_tax_amount]
    )->all())->toBe([$outputTaxType->id => 500])
        ->and($response->viewData('expenseTaxTypes')->mapWithKeys(
            fn (Tax $tax) => [$tax->tax_type_id => (int) $tax->total_tax_amount]
        )->all())->toBe([$inputTaxType->id => 200]);
});

test('keeps output tax values available to custom tax summary templates', function () {
    $outputTaxType = TaxType::factory()->create(['company_id' => $this->company->id]);
    $inputTaxType = TaxType::factory()->create([
        'company_id' => $this->company->id,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $invoice = reportInvoice($this->company->id, '2026-01-15', Invoice::STATUS_PAID);
    $expense = reportExpense($this->company->id, '2026-01-15');

    reportTax($outputTaxType, $this->company->id, ['invoice_id' => $invoice->id, 'base_amount' => 500]);
    reportTax($inputTaxType, $this->company->id, ['expense_id' => $expense->id, 'base_amount' => 200]);

    $response = taxSummaryPreview($this->company->unique_hash);

    expect($response->viewData('taxTypes'))->toHaveCount(1)
        ->and($response->viewData('totalTaxAmount'))->toBe(500)
        ->and($response->viewData('expenseTaxTypes'))->toHaveCount(1)
        ->and($response->viewData('totalExpenseTaxAmount'))->toBe(200)
        ->and($response->viewData('netTaxAmount'))->toBe(300);
});

test('calculates the correct signed net tax state', function (int $output, int $input, int $net, string $label) {
    $outputTaxType = TaxType::factory()->create(['company_id' => $this->company->id]);
    $inputTaxType = TaxType::factory()->create([
        'company_id' => $this->company->id,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    if ($output > 0) {
        $invoice = reportInvoice($this->company->id, '2026-01-15', Invoice::STATUS_PAID);
        reportTax($outputTaxType, $this->company->id, ['invoice_id' => $invoice->id, 'base_amount' => $output]);
    }

    if ($input > 0) {
        $expense = reportExpense($this->company->id, '2026-01-15');
        reportTax($inputTaxType, $this->company->id, ['expense_id' => $expense->id, 'base_amount' => $input]);
    }

    $response = taxSummaryPreview($this->company->unique_hash);

    $response->assertOk()
        ->assertViewHas('netTaxAmount', $net)
        ->assertSee(__($label));
})->with([
    'payable' => [500, 200, 300, 'pdf_tax_payable_label'],
    'refundable' => [200, 500, -300, 'pdf_tax_refundable_label'],
    'balanced' => [500, 500, 0, 'pdf_tax_balance_label'],
]);

test('reports a zero balance when the selected range has no taxes', function () {
    $response = taxSummaryPreview($this->company->unique_hash);

    $response->assertOk()
        ->assertViewHas('totalTaxAmount', 0)
        ->assertViewHas('totalExpenseTaxAmount', 0)
        ->assertViewHas('netTaxAmount', 0)
        ->assertSee(__('pdf_tax_balance_label'));

    expect($response->viewData('taxTypes'))->toBeEmpty()
        ->and($response->viewData('expenseTaxTypes'))->toBeEmpty();
});
