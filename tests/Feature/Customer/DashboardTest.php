<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Estimate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $customer = Customer::factory()->create();

    Sanctum::actingAs(
        $customer,
        ['*'],
        'customer'
    );
});

test('customer dashboard', function () {
    $customer = Auth::guard('customer')->user();

    getJson("api/v1/{$customer->company->slug}/customer/dashboard")->assertOk();
});

test('customer dashboard without active filter', function () {
    $customer = Auth::guard('customer')->user();

    $response = getJson("api/v1/{$customer->company->slug}/customer/dashboard")
        ->assertOk()
        ->assertJsonStructure([
            'due_amount',
            'recentInvoices',
            'recentEstimates',
            'invoice_count',
            'estimate_count',
            'payment_count',
            'active_filter_applied'
        ]);

    expect($response->json('active_filter_applied'))->toBeFalse();
});

test('customer dashboard with active filter enabled', function () {
    $customer = Auth::guard('customer')->user();

    $response = getJson("api/v1/{$customer->company->slug}/customer/dashboard?active_only=true")
        ->assertOk()
        ->assertJsonStructure([
            'due_amount',
            'recentInvoices',
            'recentEstimates',
            'invoice_count',
            'estimate_count',
            'payment_count',
            'active_filter_applied'
        ]);

    expect($response->json('active_filter_applied'))->toBeTrue();
});

test('customer dashboard filters invoices correctly when active filter is enabled', function () {
    $customer = Auth::guard('customer')->user();

    // Create invoices with different statuses for this customer
    $draftInvoice = Invoice::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
    ]);

    $sentInvoice = Invoice::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'status' => Invoice::STATUS_SENT,
        'paid_status' => Invoice::STATUS_UNPAID,
    ]);

    $paidInvoice = Invoice::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'status' => Invoice::STATUS_COMPLETED,
        'paid_status' => Invoice::STATUS_PAID,
    ]);

    // Test without filter
    $responseWithoutFilter = getJson("api/v1/{$customer->company->slug}/customer/dashboard")
        ->assertOk();
    $totalWithoutFilter = $responseWithoutFilter->json('invoice_count');

    // Test with filter - should only include active invoices
    $responseWithFilter = getJson("api/v1/{$customer->company->slug}/customer/dashboard?active_only=true")
        ->assertOk();
    $totalWithFilter = $responseWithFilter->json('invoice_count');

    // Active filter should show fewer or equal invoices
    expect($totalWithFilter)->toBeLessThanOrEqual($totalWithoutFilter);
    expect($responseWithFilter->json('active_filter_applied'))->toBeTrue();
});

test('customer dashboard filters estimates correctly when active filter is enabled', function () {
    $customer = Auth::guard('customer')->user();

    // Create estimates with different statuses for this customer
    $draftEstimate = Estimate::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'status' => Estimate::STATUS_DRAFT,
    ]);

    $sentEstimate = Estimate::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'status' => Estimate::STATUS_SENT,
    ]);

    $acceptedEstimate = Estimate::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'status' => Estimate::STATUS_ACCEPTED,
    ]);

    // Test without filter
    $responseWithoutFilter = getJson("api/v1/{$customer->company->slug}/customer/dashboard")
        ->assertOk();
    $totalWithoutFilter = $responseWithoutFilter->json('estimate_count');

    // Test with filter - should only include sent/viewed estimates
    $responseWithFilter = getJson("api/v1/{$customer->company->slug}/customer/dashboard?active_only=true")
        ->assertOk();
    $totalWithFilter = $responseWithFilter->json('estimate_count');

    // Active filter should show fewer or equal estimates
    expect($totalWithFilter)->toBeLessThanOrEqual($totalWithoutFilter);
    expect($responseWithFilter->json('active_filter_applied'))->toBeTrue();
});

test('customer dashboard handles boolean parameter variations correctly', function () {
    $customer = Auth::guard('customer')->user();

    // Test different ways to pass the active_only parameter
    $testCases = [
        'active_only=1',
        'active_only=true',
        'active_only=on',
        'active_only=yes',
    ];

    foreach ($testCases as $param) {
        $response = getJson("api/v1/{$customer->company->slug}/customer/dashboard?{$param}")
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
        $url = $param 
            ? "api/v1/{$customer->company->slug}/customer/dashboard?{$param}" 
            : "api/v1/{$customer->company->slug}/customer/dashboard";
        
        $response = getJson($url)->assertOk();
        
        expect($response->json('active_filter_applied'))->toBeFalse();
    }
});
