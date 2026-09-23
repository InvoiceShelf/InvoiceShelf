<?php

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->company = User::where('role', 'super admin')->first()->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
});

test('only the owner may run the exchange-rate backfill', function () {
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->company->id);

    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->company->id);
    Sanctum::actingAs($member, ['*']);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => 1, 'exchange_rate' => 2]],
    ])->assertForbidden();

    expect(CompanySetting::getSetting('bulk_exchange_rate_configured', $this->company->id))->toBe('NO');
});
