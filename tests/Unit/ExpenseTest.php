<?php

use App\Models\Company;
use App\Models\Expense;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('expense belongs to category', function () {
    $expense = Expense::factory()->forCategory()->create();

    $this->assertTrue($expense->category()->exists());
});

test('expense belongs to customer', function () {
    $expense = Expense::factory()->forCustomer()->create();

    $this->assertTrue($expense->customer()->exists());
});

test('expense belongs to company', function () {
    $company = Company::factory()->create();
    $expense = Expense::factory()->forCompany($company)->create();

    $this->assertTrue($expense->company()->exists());
});
