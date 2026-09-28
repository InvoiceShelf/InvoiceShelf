<?php

use App\Domains\Accounts\Application\RolePresetService;
use App\Domains\Accounts\Models\User;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(fn () => purchaseFixtures($this));

/**
 * A member of the fixture company holding one of its preset copies.
 */
function purchasePresetMember($test, string $roleName): User
{
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($test->companyId);
    BouncerFacade::scope()->onceTo($test->companyId, fn () => $member->assign($roleName));
    BouncerFacade::refresh();

    return $member;
}

test('Manager can work with bills and Read only can only see them', function () {
    app(RolePresetService::class)->applyDefaults();

    Sanctum::actingAs(purchasePresetMember($this, 'preset:manager'));
    $this->getJson('/api/v1/bills')->assertOk();
    $this->postJson('/api/v1/bills', purchaseBillPayload($this))->assertSuccessful();

    Sanctum::actingAs(purchasePresetMember($this, 'preset:read-only'));
    $this->getJson('/api/v1/bills')->assertOk();
    $this->getJson('/api/v1/suppliers')->assertOk();
    $this->postJson('/api/v1/bills', purchaseBillPayload($this))->assertForbidden();
});
