<?php

use App\Http\Controllers\V1\Admin\Expense\ExpensesController;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\User;
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
        'base_amount' => 11432.6247,
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
        'expense_date' => '2019-02-05',
    ]);

    getJson("api/v1/expenses/{$expense->id}")->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
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
        'base_amount' => 11432.6247,
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
