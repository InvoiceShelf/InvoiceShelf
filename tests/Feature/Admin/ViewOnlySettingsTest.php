<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * Units, payment methods and expense categories have no abilities of their
 * own. Seeing them follows the view ability of what they belong to, changing
 * them follows its edit ability, so a view-only role stays view-only.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->companyId = User::find(1)->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
});

/**
 * A member of the company holding a role with just these abilities.
 */
function memberWith(int $companyId, string $role, array $abilities): User
{
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($companyId);

    BouncerFacade::scope()->to($companyId);
    BouncerFacade::role()->firstOrCreate(['name' => $role, 'title' => $role, 'scope' => $companyId]);
    foreach ($abilities as [$ability, $model]) {
        BouncerFacade::allow($role)->to($ability, $model);
    }
    BouncerFacade::assign($role)->to($member);
    BouncerFacade::refresh();

    return $member;
}

dataset('settings', [
    'units' => ['api/v1/units', Unit::class, Item::class, 'item'],
    'payment methods' => ['api/v1/payment-methods', PaymentMethod::class, Payment::class, 'payment'],
    'expense categories' => ['api/v1/categories', ExpenseCategory::class, Expense::class, 'expense'],
]);

test('a view-only member can list but not change them', function (string $path, string $model, string $owner, string $noun) {
    $record = $model::factory()->create(['company_id' => $this->companyId]);
    Sanctum::actingAs(memberWith($this->companyId, "view-{$noun}-only", [["view-{$noun}", $owner]]), ['*']);

    getJson($path)->assertOk();
    postJson($path, ['name' => 'Added by a viewer'])->assertForbidden();
    putJson("{$path}/{$record->id}", ['name' => 'Renamed by a viewer'])->assertForbidden();
    deleteJson("{$path}/{$record->id}")->assertForbidden();
})->with('settings');

test('a member who may edit can change them', function (string $path, string $model, string $owner, string $noun) {
    Sanctum::actingAs(memberWith($this->companyId, "edit-{$noun}", [["view-{$noun}", $owner], ["edit-{$noun}", $owner]]), ['*']);

    postJson($path, ['name' => 'Added by an editor'])->assertSuccessful();
})->with('settings');

test('a member who may only create can add one inline but not change them', function (string $path, string $model, string $owner, string $noun) {
    $record = $model::factory()->create(['company_id' => $this->companyId]);
    Sanctum::actingAs(memberWith($this->companyId, "create-{$noun}", [["view-{$noun}", $owner], ["create-{$noun}", $owner]]), ['*']);

    postJson($path, ['name' => 'Added from a form'])->assertSuccessful();
    putJson("{$path}/{$record->id}", ['name' => 'Renamed'])->assertForbidden();
    deleteJson("{$path}/{$record->id}")->assertForbidden();
})->with('settings');
