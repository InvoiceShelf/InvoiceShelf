<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $this->company = Company::query()->first();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->invoice = Invoice::factory()->create(['company_id' => $this->company->id, 'customer_id' => $this->customer->id]);
    $this->estimate = Estimate::factory()->create(['company_id' => $this->company->id, 'customer_id' => $this->customer->id]);
    $this->payment = Payment::factory()->create(['company_id' => $this->company->id, 'customer_id' => $this->customer->id]);
});

function documentPdfUrls(object $test): array
{
    return [
        '/invoices/pdf/'.$test->invoice->unique_hash.'?preview',
        '/estimates/pdf/'.$test->estimate->unique_hash.'?preview',
        '/payments/pdf/'.$test->payment->unique_hash.'?preview',
    ];
}

test('a customer opens their own documents', function () {
    Sanctum::actingAs($this->customer, ['*'], 'customer');

    foreach (documentPdfUrls($this) as $url) {
        get($url)->assertOk();
    }
});

test('a customer cannot open another customer\'s documents', function () {
    Sanctum::actingAs(Customer::factory()->create(['company_id' => $this->company->id]), ['*'], 'customer');

    foreach (documentPdfUrls($this) as $url) {
        get($url)->assertNotFound();
    }
});

test('the owner opens the company\'s documents', function () {
    Sanctum::actingAs(User::query()->find(1), ['*']);

    foreach (documentPdfUrls($this) as $url) {
        get($url)->assertOk();
    }
});

test('a user from another company cannot open them', function () {
    $outsider = User::factory()->create(['role' => 'user']);
    $outsider->companies()->attach(Company::factory()->create()->id);
    Sanctum::actingAs($outsider, ['*']);

    foreach (documentPdfUrls($this) as $url) {
        get($url)->assertNotFound();
    }
});

test('a member without the view ability cannot open them', function () {
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->company->id);
    Sanctum::actingAs($member, ['*']);

    foreach (documentPdfUrls($this) as $url) {
        get($url)->assertNotFound();
    }
});
