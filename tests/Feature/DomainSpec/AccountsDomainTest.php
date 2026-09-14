<?php

// Domain behavioural suite — Accounts (spec: accounts-domain-spec.md).

use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $this->owner = User::where('role', 'super admin')->first();
    $this->companyId = $this->owner->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);
});

it('provisions a new company with the documented defaults', function () {
    $countryId = DB::table('countries')->value('id');
    $currency = DB::table('currencies')->where('code', 'EUR')->value('id');

    $company = postJson('/api/v1/companies', [
        'name' => 'Fresh GmbH', 'currency' => $currency, 'address' => ['country_id' => $countryId],
    ])->assertSuccessful()->json('data');

    $settings = DB::table('company_settings')->where('company_id', $company['id'])
        ->pluck('value', 'option');
    expect($settings['time_zone'])->toBe('Asia/Kolkata');
    expect($settings['language'])->toBe('en');
    expect($settings['fiscal_year'])->toBe('1-12');
    expect($settings['invoice_number_format'])->toBe('{{SERIES:INV}}{{DELIMITER:-}}{{SEQUENCE:6}}');
    expect($settings['bulk_exchange_rate_configured'])->toBe('YES');
    expect((int) $settings['currency'])->toBe((int) $currency);

    expect(DB::table('payment_methods')->where('company_id', $company['id'])->pluck('name')->sort()->values()->all())
        ->toBe(['Bank Transfer', 'Cash', 'Check', 'Credit Card']);
    expect(DB::table('units')->where('company_id', $company['id'])->count())->toBe(11);

    $roleId = DB::table('roles')->where('scope', $company['id'])->where('name', 'owner')->value('id');
    expect($roleId)->not->toBeNull();
    expect(DB::table('permissions')->where('entity_id', $roleId)->count())
        ->toBe(count(app(AbilityCatalog::class)->all()));
    expect((int) $company['owner_id'])->toBe($this->owner->id);
});

it('flips owner-only authorization when ownership is transferred', function () {
    postJson('/api/v1/members', ['name' => 'Heir', 'email' => 'heir@x.test', 'password' => 'secret123',
        'companies' => [['id' => $this->companyId, 'role' => 'owner']]])->assertSuccessful();
    $heir = User::where('email', 'heir@x.test')->first();

    postJson('/api/v1/company/settings', ['settings' => ['language' => 'de']])->assertOk();

    postJson("/api/v1/transfer/ownership/{$heir->id}")->assertOk()->assertJson(['success' => true]);

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->owner->fresh(), ['*']);
    postJson('/api/v1/company/settings', ['settings' => ['language' => 'fr']])->assertForbidden();

    app('auth')->forgetGuards();
    Sanctum::actingAs($heir, ['*']);
    $this->withHeaders(['company' => $this->companyId]);
    postJson('/api/v1/company/settings', ['settings' => ['language' => 'mk']])->assertOk();
});

it('locks the company currency once transactions exist', function () {
    $eur = DB::table('currencies')->where('code', 'EUR')->value('id');
    postJson('/api/v1/company/settings', ['settings' => ['currency' => (string) $eur]])
        ->assertOk()->assertJson(['success' => true]);

    postJson('/api/v1/customers', ['name' => 'Tx Customer'])->assertSuccessful();

    $usd = DB::table('currencies')->where('code', 'USD')->value('id');
    postJson('/api/v1/company/settings', ['settings' => ['currency' => (string) $usd]])
        ->assertOk()->assertJson(['success' => false,
            'message' => 'Cannot update company currency after transactions are created.']);
});

it('requires the exact company name to delete, and never deletes users', function () {
    postJson('/api/v1/companies/delete', ['name' => 'wrong'])
        ->assertStatus(422)->assertJson(['error' => 'company_name_must_match_with_given_name']);

    $name = DB::table('companies')->where('id', $this->companyId)->value('name');
    $usersBefore = DB::table('users')->count();
    postJson('/api/v1/companies/delete', ['name' => $name])->assertOk()->assertJson(['success' => true]);

    expect(DB::table('companies')->where('id', $this->companyId)->exists())->toBeFalse();
    expect(DB::table('users')->count())->toBe($usersBefore);
});

it('silently swaps a foreign company header for the user’s first company', function () {
    postJson('/api/v1/customers', ['name' => 'Visible'])->assertSuccessful();

    $this->withHeaders(['company' => 999]);
    $names = collect(getJson('/api/v1/customers')->assertOk()->json('data'))->pluck('name');
    expect($names)->toContain('Visible');
});

it('excludes the requester from the member list but counts them in the meta', function () {
    postJson('/api/v1/members', ['name' => 'M1', 'email' => 'm1@x.test', 'password' => 'secret123',
        'companies' => [['id' => $this->companyId, 'role' => 'owner']]])->assertSuccessful();
    postJson('/api/v1/members', ['name' => 'M2', 'email' => 'm2@x.test', 'password' => 'secret123',
        'companies' => [['id' => $this->companyId, 'role' => 'owner']]])->assertSuccessful();

    $payload = getJson('/api/v1/members')->assertOk()->json();
    expect(collect($payload['data'])->pluck('email'))->not->toContain($this->owner->email);
    expect(count($payload['data']))->toBe(2);
    expect($payload['meta']['user_total_count'])->toBe(3);
});

it('scopes the role listing to the active company through the role-scope mechanism', function () {
    $countryId = DB::table('countries')->value('id');
    $eur = DB::table('currencies')->where('code', 'EUR')->value('id');
    $second = postJson('/api/v1/companies', [
        'name' => 'Second Co', 'currency' => $eur, 'address' => ['country_id' => $countryId],
    ])->assertSuccessful()->json('data');
    expect(DB::table('roles')->distinct()->count('scope'))->toBeGreaterThan(1);

    // Despite taking a company_id filter, the listing is bounded by the active
    // company's role scope: the default listing shows only the active company's
    // roles, and a foreign company_id can only narrow that to nothing.
    $default = collect(getJson('/api/v1/roles')->assertOk()->json('data'));
    expect($default->count())->toBe(DB::table('roles')->where('scope', $this->companyId)->count());

    $foreign = collect(getJson('/api/v1/roles?company_id='.$second['id'])->json('data'));
    expect($foreign->count())->toBe(0);
});

it('syncs role abilities as grant-listed, revoke-unlisted', function () {
    $abilities = fn (array $names) => array_map(fn ($a) => ['ability' => $a], $names);
    $role = postJson('/api/v1/roles', ['name' => 'shape-shifter',
        'abilities' => $abilities(['view-item', 'view-customer'])])->assertSuccessful()->json('data');

    $granted = collect(getJson("/api/v1/roles/{$role['id']}")->json('data.abilities'))->pluck('name');
    expect($granted)->toContain('view-item', 'view-customer')->not->toContain('view-invoice');

    putJson("/api/v1/roles/{$role['id']}", ['name' => 'shape-shifter',
        'abilities' => $abilities(['view-invoice'])])->assertSuccessful();

    // The authorization layer caches grants per process; a real deployment
    // re-reads them per request.
    BouncerFacade::refresh();
    $after = collect(getJson("/api/v1/roles/{$role['id']}")->json('data.abilities'))->pluck('name');
    expect($after)->toContain('view-invoice')->not->toContain('view-item');
});

it('logs in case-insensitively and issues a bearer token', function () {
    app('auth')->forgetGuards();
    $this->flushHeaders();

    postJson('/api/v1/auth/login', [
        'username' => 'ADMIN@InvoiceShelf.com', 'password' => 'invoiceshelf@123', 'device_name' => 'suite',
    ])->assertOk()->assertJson(['type' => 'Bearer'])->assertJsonStructure(['token']);

    postJson('/api/v1/auth/login', [
        'username' => 'admin@invoiceshelf.com', 'password' => 'wrong', 'device_name' => 'suite',
    ])->assertStatus(422)->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
});
