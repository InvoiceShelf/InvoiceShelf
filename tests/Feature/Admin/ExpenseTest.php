<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Http\Controllers\Company\ExpensesController;
use App\Domains\Purchases\Http\Requests\ExpenseRequest;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get expenses', function () {
    getJson('api/v1/expenses?page=1')->assertOk();
});

test('create expense', function () {
    $expense = Expense::factory()->raw([
        'amount' => 150,
        'exchange_rate' => 76.217498,
        'base_amount' => 11433,
    ]);

    postJson('api/v1/expenses', $expense)->assertStatus(201);

    $this->assertDatabaseHas('expenses', [
        'notes' => $expense['notes'],
        'expense_category_id' => $expense['expense_category_id'],
        'amount' => $expense['amount'],
        'exchange_rate' => $expense['exchange_rate'],
        'base_amount' => $expense['base_amount'],
    ]);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ExpensesController::class,
        'store',
        ExpenseRequest::class
    );
});

test('get expense data', function () {
    $expense = Expense::factory()->create([
        'expense_number' => 'EXP-000001',
        'expense_date' => '2019-02-05',
    ]);

    getJson("api/v1/expenses/{$expense->id}")->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'expense_number' => $expense['expense_number'],
        'notes' => $expense['notes'],
        'expense_category_id' => $expense['expense_category_id'],
        'amount' => $expense['amount'],
    ]);
});

test('update expense', function () {
    $expense = Expense::factory()->create([
        'expense_date' => '2019-02-05',
    ]);

    $expense2 = Expense::factory()->raw();

    putJson('api/v1/expenses/'.$expense->id, $expense2)->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'notes' => $expense2['notes'],
        'expense_category_id' => $expense2['expense_category_id'],
        'amount' => $expense2['amount'],
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ExpensesController::class,
        'update',
        ExpenseRequest::class
    );
});

test('search expenses', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'expense_category_id' => 1,
        'search' => 'cate',
        'from_date' => '2020-07-18',
        'to_date' => '2020-07-20',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/expenses?'.$queryString);

    $response->assertOk();
});

test('delete multiple expenses', function () {
    $expenses = Expense::factory()->count(3)->create([
        'expense_date' => '2019-02-05',
    ]);

    $data = [
        'ids' => $expenses->pluck('id'),
    ];

    $response = postJson('api/v1/expenses/delete', $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    foreach ($expenses as $expense) {
        $this->assertModelMissing($expense);
    }
});

test('update expense with EUR currency', function () {
    $expense = Expense::factory()->create([
        'expense_date' => '2019-02-05',
    ]);

    $expense2 = Expense::factory()->raw([
        'amount' => 150,
        'exchange_rate' => 76.217498,
        'base_amount' => 11433,
    ]);

    putJson('api/v1/expenses/'.$expense->id, $expense2)->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'expense_category_id' => $expense2['expense_category_id'],
        'amount' => $expense2['amount'],
        'exchange_rate' => $expense2['exchange_rate'],
        'base_amount' => $expense2['base_amount'],
    ]);
});

test('creates receipt tax snapshots from configured tax types', function () {
    $companyId = User::find(1)->companies()->first()->id;
    $percentageTax = TaxType::factory()->create([
        'company_id' => $companyId,
        'name' => 'VAT',
        'percent' => 18.5,
        'calculation_type' => 'percentage',
        'fixed_amount' => null,
        'compound_tax' => true,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $fixedTax = TaxType::factory()->create([
        'company_id' => $companyId,
        'name' => 'Stamp duty',
        'percent' => null,
        'calculation_type' => 'fixed',
        'fixed_amount' => 25,
        'compound_tax' => false,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $expense = Expense::factory()->raw([
        'amount' => 100,
        'exchange_rate' => 1.2345,
        'currency_id' => 1,
        'base_amount' => 0,
    ]);
    $expense['taxes'] = json_encode([
        ['tax_type_id' => $percentageTax->id, 'amount' => 35],
        ['tax_type_id' => $fixedTax->id, 'amount' => 10],
    ]);

    $response = postJson('api/v1/expenses', $expense)->assertCreated();

    $expenseId = $response->json('data.id');

    $this->assertDatabaseHas('taxes', [
        'expense_id' => $expenseId,
        'tax_type_id' => $percentageTax->id,
        'name' => 'VAT',
        'amount' => 35,
        'base_amount' => 43,
        'percent' => 18.5,
        'calculation_type' => 'percentage',
        'compound_tax' => 1,
        'company_id' => $companyId,
        'currency_id' => 1,
    ]);
    $this->assertDatabaseHas('taxes', [
        'expense_id' => $expenseId,
        'tax_type_id' => $fixedTax->id,
        'name' => 'Stamp duty',
        'amount' => 10,
        'base_amount' => 12,
        'fixed_amount' => 25,
        'calculation_type' => 'fixed',
    ]);
    $this->assertDatabaseHas('expenses', [
        'id' => $expenseId,
        'amount' => 100,
    ]);
    $response->assertJsonPath('data.taxes.0.expense_id', $expenseId);
});

test('updates receipt taxes only when taxes are submitted', function () {
    $companyId = User::find(1)->companies()->first()->id;
    $oldTaxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $newTaxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $expense = Expense::factory()->create(['amount' => 100]);
    Tax::factory()->create([
        'expense_id' => $expense->id,
        'tax_type_id' => $oldTaxType->id,
        'company_id' => $companyId,
        'amount' => 10,
    ]);
    $payload = Expense::factory()->raw(['amount' => 100]);

    putJson("api/v1/expenses/{$expense->id}", $payload)->assertOk();
    expect(Tax::where('expense_id', $expense->id)->count())->toBe(1);

    $payload['taxes'] = [['tax_type_id' => $newTaxType->id, 'amount' => 20]];
    putJson("api/v1/expenses/{$expense->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.taxes.0.tax_type_id', $newTaxType->id);

    $this->assertDatabaseMissing('taxes', ['expense_id' => $expense->id, 'tax_type_id' => $oldTaxType->id]);
    $this->assertDatabaseHas('taxes', ['expense_id' => $expense->id, 'tax_type_id' => $newTaxType->id, 'amount' => 20]);

    $payload['taxes'] = [];
    putJson("api/v1/expenses/{$expense->id}", $payload)->assertOk();
    expect(Tax::where('expense_id', $expense->id)->count())->toBe(0);
});

test('validates malformed and invalid receipt taxes', function () {
    $companyId = User::find(1)->companies()->first()->id;
    $taxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $salesTaxType = TaxType::factory()->create(['company_id' => $companyId]);
    $otherCompany = Company::factory()->create();
    $otherCompanyTaxType = TaxType::factory()->create([
        'company_id' => $otherCompany->id,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $expense = Expense::factory()->raw(['amount' => 10]);

    postJson('api/v1/expenses', array_merge($expense, ['taxes' => '{not valid json']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taxes');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => ['not an object']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taxes.0');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => [
        ['tax_type_id' => $taxType->id, 'amount' => 1],
        ['tax_type_id' => $taxType->id, 'amount' => 1],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('taxes.1.tax_type_id');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => [
        ['tax_type_id' => $otherCompanyTaxType->id, 'amount' => 1],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('taxes.0.tax_type_id');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => [
        ['tax_type_id' => $salesTaxType->id, 'amount' => 1],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('taxes.0.tax_type_id');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => [
        ['tax_type_id' => $taxType->id, 'amount' => -1],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('taxes.0.amount');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => [
        ['tax_type_id' => $taxType->id, 'amount' => 11],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('taxes');
    postJson('api/v1/expenses', array_merge($expense, ['taxes' => [
        ['tax_type_id' => $taxType->id, 'amount' => 1, 'name' => 'untrusted'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('taxes.0');
});

test('includes taxes on expense detail responses but not list responses', function () {
    $companyId = User::find(1)->companies()->first()->id;
    $expense = Expense::factory()->create();
    $taxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    Tax::factory()->create([
        'expense_id' => $expense->id,
        'tax_type_id' => $taxType->id,
        'company_id' => $companyId,
    ]);

    getJson("api/v1/expenses/{$expense->id}")
        ->assertOk()
        ->assertJsonPath('data.taxes.0.expense_id', $expense->id);

    $list = getJson('api/v1/expenses?page=1')->assertOk()->json('data.0');
    expect($list)->not->toHaveKey('taxes');
});

test('deleting expenses removes their receipt taxes', function () {
    $companyId = User::find(1)->companies()->first()->id;
    $expense = Expense::factory()->create();
    $taxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $tax = Tax::factory()->create([
        'expense_id' => $expense->id,
        'company_id' => $companyId,
        'tax_type_id' => $taxType->id,
    ]);

    postJson('api/v1/expenses/delete', ['ids' => [$expense->id]])->assertOk();

    $this->assertDatabaseMissing('taxes', ['id' => $tax->id]);
});
