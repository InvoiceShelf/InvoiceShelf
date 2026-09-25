<?php

// Domain behavioural suite — Catalog (spec: catalog-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

it('stamps the item currency from the company setting, ignoring the payload', function () {
    $companyCurrency = (int) DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'currency')->value('value');
    $other = DB::table('currencies')->where('id', '!=', $companyCurrency)->value('id');

    $item = postJson('/api/v1/items', ['name' => 'Widget', 'price' => 500, 'currency_id' => $other])
        ->assertSuccessful()->json('data');

    expect((int) $item['currency_id'])->toBe($companyCurrency);
});

it('raises the per-item-tax flag on attach and never lowers it', function () {
    $typeId = postJson('/api/v1/tax-types', ['name' => 'ItemTax', 'calculation_type' => 'percentage', 'percent' => 10])
        ->json('data.id');

    $id = postJson('/api/v1/items', ['name' => 'Flagged', 'price' => 100])->json('data.id');
    expect((bool) DB::table('items')->where('id', $id)->value('tax_per_item'))->toBeFalse();

    putJson("/api/v1/items/{$id}", ['name' => 'Flagged', 'price' => 100,
        'taxes' => [['tax_type_id' => $typeId, 'name' => 'ItemTax', 'percent' => 10, 'amount' => 10]],
    ])->assertSuccessful();
    expect((bool) DB::table('items')->where('id', $id)->value('tax_per_item'))->toBeTrue();

    // Replacing with an empty list removes the taxes but leaves the flag raised.
    putJson("/api/v1/items/{$id}", ['name' => 'Flagged', 'price' => 100, 'taxes' => []])->assertSuccessful();
    expect(DB::table('taxes')->where('item_id', $id)->count())->toBe(0);
    expect((bool) DB::table('items')->where('id', $id)->value('tax_per_item'))->toBeTrue();
});

it('blocks bulk deletion of items that still carry their own taxes', function () {
    $typeId = postJson('/api/v1/tax-types', ['name' => 'BlockTax', 'calculation_type' => 'percentage', 'percent' => 5])
        ->json('data.id');
    $id = postJson('/api/v1/items', ['name' => 'Undeletable', 'price' => 100,
        'taxes' => [['tax_type_id' => $typeId, 'name' => 'BlockTax', 'percent' => 5, 'amount' => 5]],
    ])->json('data.id');

    postJson('/api/v1/items/delete', ['ids' => [$id]])->assertStatus(422);

    putJson("/api/v1/items/{$id}", ['name' => 'Undeletable', 'price' => 100, 'taxes' => []])->assertSuccessful();
    postJson('/api/v1/items/delete', ['ids' => [$id]])->assertOk()->assertJson(['success' => true]);
    expect(DB::table('items')->where('id', $id)->exists())->toBeFalse();
});

it('resolves unit names on the listing and returns only sales tax types in meta', function () {
    postJson('/api/v1/tax-types', ['name' => 'SalesT', 'calculation_type' => 'percentage', 'percent' => 1])->assertSuccessful();
    postJson('/api/v1/tax-types', ['name' => 'PurchT', 'calculation_type' => 'percentage', 'percent' => 2,
        'transaction_type' => 'purchases'])->assertSuccessful();

    $unitId = DB::table('units')->where('company_id', $this->companyId)->value('id');
    postJson('/api/v1/items', ['name' => 'WithUnit', 'price' => 10, 'unit_id' => $unitId])->assertSuccessful();
    postJson('/api/v1/items', ['name' => 'NoUnit', 'price' => 10])->assertSuccessful();

    $payload = getJson('/api/v1/items?limit=all')->assertOk()->json();
    $byName = collect($payload['data'])->keyBy('name');
    expect($byName['WithUnit']['unit']['name'] ?? null)->not->toBeNull();
    expect($byName['NoUnit']['unit'] ?? null)->toBeNull();
    $metaTypes = collect($payload['meta']['tax_types'])->pluck('name');
    expect($metaTypes)->toContain('SalesT')->not->toContain('PurchT');
});

it('enforces unit name uniqueness per company and refuses deleting a used unit', function () {
    $unitId = postJson('/api/v1/units', ['name' => 'crate'])->assertSuccessful()->json('data.id');
    postJson('/api/v1/units', ['name' => 'crate'])->assertStatus(422)->assertJsonValidationErrors(['name']);

    postJson('/api/v1/items', ['name' => 'Crated', 'price' => 10, 'unit_id' => $unitId])->assertSuccessful();
    deleteJson("/api/v1/units/{$unitId}")->assertStatus(422)->assertJson(['error' => 'items_attached']);

    $freeId = postJson('/api/v1/units', ['name' => 'pallet'])->json('data.id');
    deleteJson("/api/v1/units/{$freeId}")->assertOk()
        ->assertJson(['success' => 'Unit deleted successfully']);
});

it('lists units on the item view ability and adds them on create or edit', function () {
    $abilities = fn (array $names) => array_map(fn ($a) => ['ability' => $a], $names);

    postJson('/api/v1/roles', ['name' => 'item-viewer', 'abilities' => $abilities(['view-item'])])->assertSuccessful();
    postJson('/api/v1/roles', ['name' => 'item-editor', 'abilities' => $abilities(['edit-item', 'create-item'])])->assertSuccessful();

    postJson('/api/v1/members', ['name' => 'Viewer', 'email' => 'viewer@x.test', 'password' => 'secret123',
        'companies' => [['id' => $this->companyId, 'role' => 'item-viewer']]])->assertSuccessful();
    postJson('/api/v1/members', ['name' => 'Editor', 'email' => 'editor@x.test', 'password' => 'secret123',
        'companies' => [['id' => $this->companyId, 'role' => 'item-editor']]])->assertSuccessful();

    Sanctum::actingAs(User::where('email', 'viewer@x.test')->first(), ['*']);
    getJson('/api/v1/units')->assertOk();
    postJson('/api/v1/units', ['name' => 'viewer-made'])->assertForbidden();

    Sanctum::actingAs(User::where('email', 'editor@x.test')->first(), ['*']);
    getJson('/api/v1/units')->assertForbidden();
    postJson('/api/v1/units', ['name' => 'editor-made'])->assertSuccessful();
});
