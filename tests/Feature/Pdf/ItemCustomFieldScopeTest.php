<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * Line-level custom fields become column headers on the rendered document, so
 * the lookup that collects them has to be scoped to the company the document
 * belongs to. It was not, and every tenant's field labels appeared on every
 * other tenant's invoices and estimates.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->otherCompany = Company::factory()->create();

    Sanctum::actingAs($user, ['*']);

    CustomField::factory()->create([
        'company_id' => $this->company->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'label' => 'Service Period',
    ]);

    CustomField::factory()->create([
        'company_id' => $this->otherCompany->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'label' => 'Someone Elses Column',
    ]);
});

test('an invoice prints only its own company line-level fields', function () {
    $invoice = Invoice::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => 'invoice1',
    ]);

    $html = get("/invoices/pdf/{$invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->toContain('Service Period')
        ->and($html)->not->toContain('Someone Elses Column');
});

test('an estimate prints only its own company line-level fields', function () {
    $estimate = Estimate::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => 'estimate1',
    ]);

    $html = get("/estimates/pdf/{$estimate->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->toContain('Service Period')
        ->and($html)->not->toContain('Someone Elses Column');
});
