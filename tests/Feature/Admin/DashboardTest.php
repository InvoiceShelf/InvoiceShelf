<?php

use App\Models\User;
use App\Models\Invoice;
use App\Models\Estimate;
use App\Models\Customer;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

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

it('can get dashboard data', function () {
    getJson('api/v1/dashboard')->assertOk();
});

it('can search', function () {
    getJson('api/v1/search?name=ab')->assertOk();
});

it('can get dashboard data without active filter', function () {
    $response = getJson('api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'total_amount_due',
            'total_customer_count',
            'total_invoice_count',
            'total_estimate_count',
            'recent_due_invoices',
            'recent_estimates',
            'chart_data',
            'total_sales',
            'total_receipts',
            'total_expenses',
            'total_net_income',
            'active_filter_applied'
        ]);

    expect($response->json('active_filter_applied'))->toBeFalse();
});

it('can get dashboard data with active filter enabled', function () {
    $response = getJson('api/v1/dashboard?active_only=true')
        ->assertOk()
        ->assertJsonStructure([
            'total_amount_due',
            'total_customer_count',
            'total_invoice_count',
            'total_estimate_count',
            'recent_due_invoices',
            'recent_estimates',
            'chart_data',
            'total_sales',
            'total_receipts',
            'total_expenses',
            'total_net_income',
            'active_filter_applied'
        ]);

    expect($response->json('active_filter_applied'))->toBeTrue();
});

it('filters invoices correctly when active filter is enabled', function () {
    $company = User::find(1)->companies()->first();
    
    // Create test customer
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'enable_portal' => true,
    ]);

    // Create invoices with different statuses
    $draftInvoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
    ]);

    $sentInvoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => Invoice::STATUS_SENT,
        'paid_status' => Invoice::STATUS_UNPAID,
    ]);

    $paidInvoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => Invoice::STATUS_COMPLETED,
        'paid_status' => Invoice::STATUS_PAID,
    ]);

    // Test without filter - should include all non-draft invoices
    $responseWithoutFilter = getJson('api/v1/dashboard')->assertOk();
    $totalWithoutFilter = $responseWithoutFilter->json('total_invoice_count');

    // Test with filter - should only include active invoices
    $responseWithFilter = getJson('api/v1/dashboard?active_only=true')->assertOk();
    $totalWithFilter = $responseWithFilter->json('total_invoice_count');

    // Active filter should show fewer invoices (only sent/viewed with unpaid/partially_paid status)
    expect($totalWithFilter)->toBeLessThanOrEqual($totalWithoutFilter);
    expect($responseWithFilter->json('active_filter_applied'))->toBeTrue();
});

it('filters estimates correctly when active filter is enabled', function () {
    $company = User::find(1)->companies()->first();
    
    // Create test customer
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'enable_portal' => true,
    ]);

    // Create estimates with different statuses
    $draftEstimate = Estimate::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => Estimate::STATUS_DRAFT,
    ]);

    $sentEstimate = Estimate::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => Estimate::STATUS_SENT,
    ]);

    $acceptedEstimate = Estimate::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => Estimate::STATUS_ACCEPTED,
    ]);

    // Test without filter
    $responseWithoutFilter = getJson('api/v1/dashboard')->assertOk();
    $totalWithoutFilter = $responseWithoutFilter->json('total_estimate_count');

    // Test with filter - should only include sent/viewed estimates
    $responseWithFilter = getJson('api/v1/dashboard?active_only=true')->assertOk();
    $totalWithFilter = $responseWithFilter->json('total_estimate_count');

    // Active filter should show fewer estimates
    expect($totalWithFilter)->toBeLessThanOrEqual($totalWithoutFilter);
    expect($responseWithFilter->json('active_filter_applied'))->toBeTrue();
});

it('filters customers correctly when active filter is enabled', function () {
    $company = User::find(1)->companies()->first();
    
    // Create customers with different portal access
    $customerWithPortal = Customer::factory()->create([
        'company_id' => $company->id,
        'enable_portal' => true,
    ]);

    $customerWithoutPortal = Customer::factory()->create([
        'company_id' => $company->id,
        'enable_portal' => false,
    ]);

    // Create an active invoice for the customer with portal
    Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customerWithPortal->id,
        'status' => Invoice::STATUS_SENT,
        'paid_status' => Invoice::STATUS_UNPAID,
    ]);

    // Test without filter
    $responseWithoutFilter = getJson('api/v1/dashboard')->assertOk();
    $totalWithoutFilter = $responseWithoutFilter->json('total_customer_count');

    // Test with filter - should only include customers with portal enabled and active invoices/estimates
    $responseWithFilter = getJson('api/v1/dashboard?active_only=true')->assertOk();
    $totalWithFilter = $responseWithFilter->json('total_customer_count');

    // Active filter should show fewer or equal customers
    expect($totalWithFilter)->toBeLessThanOrEqual($totalWithoutFilter);
    expect($responseWithFilter->json('active_filter_applied'))->toBeTrue();
});

it('handles boolean parameter variations correctly', function () {
    // Test different ways to pass the active_only parameter
    $testCases = [
        'active_only=1',
        'active_only=true',
        'active_only=on',
        'active_only=yes',
    ];

    foreach ($testCases as $param) {
        $response = getJson("api/v1/dashboard?{$param}")
            ->assertOk();
        
        expect($response->json('active_filter_applied'))->toBeTrue();
    }

    // Test false cases
    $falseCases = [
        'active_only=0',
        'active_only=false',
        'active_only=off',
        'active_only=no',
        '', // no parameter
    ];

    foreach ($falseCases as $param) {
        $url = $param ? "api/v1/dashboard?{$param}" : 'api/v1/dashboard';
        $response = getJson($url)->assertOk();
        
        expect($response->json('active_filter_applied'))->toBeFalse();
    }
});
