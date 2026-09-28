<?php

// Domain behavioural suite — Contacts (spec: contacts-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->companySlug = DB::table('companies')->where('id', $this->companyId)->value('slug');
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

it('requires every search term to match name, email or phone', function () {
    postJson('/api/v1/customers', ['name' => 'Alice Wonder', 'email' => 'alice@x.test'])->assertSuccessful();
    postJson('/api/v1/customers', ['name' => 'Bob Wonder', 'email' => 'bob@x.test'])->assertSuccessful();

    $names = collect(getJson('/api/v1/customers?search='.urlencode('Wonder alice'))->assertOk()->json('data'))
        ->pluck('name');
    expect($names->all())->toBe(['Alice Wonder']);

    $both = collect(getJson('/api/v1/customers?search=Wonder')->json('data'))->pluck('name');
    expect($both)->toContain('Alice Wonder', 'Bob Wonder');
});

it('locks the customer currency once any document exists', function () {
    $usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $eur = DB::table('currencies')->where('code', 'EUR')->value('id');
    $id = postJson('/api/v1/customers', ['name' => 'Locked', 'currency_id' => $usd])->json('data.id');

    putJson("/api/v1/customers/{$id}", ['name' => 'Locked', 'currency_id' => $eur])->assertSuccessful();

    postJson('/api/v1/invoices', [
        'invoice_date' => '2026-01-10', 'customer_id' => $id, 'invoice_number' => 'INV-LOCK-1',
        'discount' => 0, 'discount_val' => 0, 'sub_total' => 100, 'total' => 100, 'tax' => 0,
        'template_name' => 'invoice1', 'exchange_rate' => 2, 'currency_id' => $eur,
        'items' => [['name' => 'X', 'quantity' => 1, 'price' => 100, 'description' => '',
            'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 100]],
    ])->assertSuccessful();

    putJson("/api/v1/customers/{$id}", ['name' => 'Locked', 'currency_id' => $usd])
        ->assertStatus(422)->assertJsonValidationErrors(['currency_id']);
});

it('replaces addresses wholesale on update — omitting them erases them', function () {
    $id = postJson('/api/v1/customers', [
        'name' => 'Addressed',
        'billing' => ['name' => 'Bill', 'city' => 'Skopje'],
        'shipping' => ['name' => 'Ship', 'city' => 'Ohrid'],
    ])->assertSuccessful()->json('data.id');
    expect(DB::table('addresses')->where('customer_id', $id)->count())->toBe(2);

    putJson("/api/v1/customers/{$id}", ['name' => 'Addressed'])->assertSuccessful();
    expect(DB::table('addresses')->where('customer_id', $id)->count())->toBe(0);
});

it('purges the customer’s documents, payments and allocations on delete', function () {
    $usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $id = postJson('/api/v1/customers', ['name' => 'Purged', 'currency_id' => $usd])->json('data.id');

    $invoiceId = postJson('/api/v1/invoices', [
        'invoice_date' => '2026-01-10', 'customer_id' => $id, 'invoice_number' => 'INV-PURGE-1',
        'discount' => 0, 'discount_val' => 0, 'sub_total' => 100, 'total' => 100, 'tax' => 0,
        'template_name' => 'invoice1', 'exchange_rate' => 2, 'currency_id' => $usd,
        'items' => [['name' => 'X', 'quantity' => 1, 'price' => 100, 'description' => '',
            'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 100]],
    ])->assertSuccessful()->json('data.id');

    postJson("/api/v1/invoices/{$invoiceId}/status", ['status' => 'SENT'])->assertOk();

    postJson('/api/v1/payments', [
        'payment_date' => '2026-01-11', 'customer_id' => $id, 'amount' => 100,
        'payment_number' => 'PAY-PURGE-1', 'exchange_rate' => 2,
        'allocations' => [['invoice_id' => $invoiceId, 'amount' => 100]],
    ])->assertSuccessful();

    postJson('/api/v1/customers/delete', ['ids' => [$id]])->assertOk()->assertJson(['success' => true]);

    expect(DB::table('invoices')->where('customer_id', $id)->count())->toBe(0);
    expect(DB::table('customer_payments')->where('customer_id', $id)->count())->toBe(0);
    expect(DB::table('customer_payment_allocations')->count())->toBe(0);
    expect(DB::table('customers')->where('id', $id)->exists())->toBeFalse();
});

it('logs portal customers in case-insensitively and distinguishes the two failure modes', function () {
    postJson('/api/v1/customers', [
        'name' => 'Portal Kate', 'email' => 'kate@portal.test', 'password' => 'secret123', 'enable_portal' => true,
    ])->assertSuccessful();

    postJson("/{$this->companySlug}/customer/login", ['email' => 'KATE@Portal.TEST', 'password' => 'secret123'])
        ->assertOk()->assertJson(['success' => true]);

    postJson("/{$this->companySlug}/customer/login", ['email' => 'kate@portal.test', 'password' => 'wrong'])
        ->assertStatus(422)->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

    postJson('/api/v1/customers', [
        'name' => 'No Portal', 'email' => 'nope@portal.test', 'password' => 'secret123', 'enable_portal' => false,
    ])->assertSuccessful();
    postJson("/{$this->companySlug}/customer/login", ['email' => 'nope@portal.test', 'password' => 'secret123'])
        ->assertStatus(422)->assertJsonPath('errors.email.0', 'Customer portal not available for this user.');
});

it('revokes portal access immediately when the flag is switched off', function () {
    $id = postJson('/api/v1/customers', [
        'name' => 'Revoked', 'email' => 'rev@portal.test', 'password' => 'secret123', 'enable_portal' => true,
    ])->json('data.id');

    postJson("/{$this->companySlug}/customer/login", ['email' => 'rev@portal.test', 'password' => 'secret123'])
        ->assertOk();
    getJson("/api/v1/{$this->companySlug}/customer/me")->assertOk();

    putJson("/api/v1/customers/{$id}", ['name' => 'Revoked', 'email' => 'rev@portal.test', 'enable_portal' => false])
        ->assertSuccessful();
    expect((int) DB::table('customers')->where('id', $id)->value('enable_portal'))->toBe(0);

    // Force guard re-resolution: in-process tests cache the resolved customer,
    // which a real per-request deployment never does.
    app('auth')->forgetGuards();
    getJson("/api/v1/{$this->companySlug}/customer/me")->assertStatus(401);
});

it('exposes a missing avatar as the number zero', function () {
    $id = postJson('/api/v1/customers', ['name' => 'Faceless'])->json('data.id');
    expect(getJson("/api/v1/customers/{$id}")->assertOk()->json('data.avatar'))->toBe(0);
});
