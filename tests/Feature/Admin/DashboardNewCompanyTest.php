<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

/**
 * A company created a moment ago has no records at all. The dashboard still
 * answers with numbers and empty lists, never nulls, so the page can draw
 * its empty state rather than guess.
 */
test('a new company gets a dashboard of zeros and empty lists', function () {
    $companyId = postJson('/api/v1/companies', Company::factory()->raw([
        'currency' => 12,
        'address' => ['country_id' => 12],
    ]))->assertStatus(201)->json('data.id');

    $response = getJson('api/v1/dashboard', ['company' => $companyId])->assertOk();

    foreach (['total_amount_due', 'total_customer_count', 'total_invoice_count', 'total_estimate_count', 'total_sales', 'total_receipts', 'total_expenses', 'total_net_income'] as $key) {
        expect((int) $response->json($key))->toBe(0, $key);
    }

    expect($response->json('recent_due_invoices'))->toBe([])
        ->and($response->json('recent_estimates'))->toBe([]);

    $chart = $response->json('chart_data');

    expect($chart['months'])->toHaveCount(12);

    foreach (['invoice_totals', 'receipt_totals', 'expense_totals', 'net_income_totals'] as $series) {
        expect($chart[$series])->toHaveCount(12)
            ->and(array_sum($chart[$series]))->toBe(0);
    }
});
