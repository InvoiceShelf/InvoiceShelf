<?php

use App\Models\User;
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

it('can access dashboard with active filter parameter', function () {
    $response = getJson('/api/v1/dashboard?active_only=true');
    
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'total_amount_due',
        'total_customer_count',
        'total_invoice_count',
        'total_estimate_count',
        'chart_data',
        'recent_due_invoices',
        'recent_estimates',
    ]);
});

it('can access dashboard without active filter parameter', function () {
    $response = getJson('/api/v1/dashboard');
    
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'total_amount_due',
        'total_customer_count',
        'total_invoice_count',
        'total_estimate_count',
        'chart_data',
        'recent_due_invoices',
        'recent_estimates',
    ]);
});

it('handles active filter parameter correctly', function () {
    // Test with active_only=true
    $response = getJson('/api/v1/dashboard?active_only=true');
    $response->assertStatus(200);
    
    // Test with active_only=false
    $response = getJson('/api/v1/dashboard?active_only=false');
    $response->assertStatus(200);
    
    // Test with active_only=1
    $response = getJson('/api/v1/dashboard?active_only=1');
    $response->assertStatus(200);
    
    // Test with active_only=0
    $response = getJson('/api/v1/dashboard?active_only=0');
    $response->assertStatus(200);
});

it('returns consistent data structure with and without filter', function () {
    $responseWithFilter = getJson('/api/v1/dashboard?active_only=true');
    $responseWithoutFilter = getJson('/api/v1/dashboard');
    
    $responseWithFilter->assertStatus(200);
    $responseWithoutFilter->assertStatus(200);
    
    // Both responses should have the same structure
    $withFilterKeys = array_keys($responseWithFilter->json());
    $withoutFilterKeys = array_keys($responseWithoutFilter->json());
    
    expect($withFilterKeys)->toEqual($withoutFilterKeys);
}); 