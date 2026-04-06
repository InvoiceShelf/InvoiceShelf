<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('customer portal guest entrypoints return the spa shell', function () {
    $customer = Customer::factory()->create();
    $companySlug = $customer->company->slug;

    $this->followingRedirects()->get("/{$companySlug}/customer")
        ->assertOk()
        ->assertSee('window.InvoiceShelf.start()', false);

    $this->followingRedirects()->get("/{$companySlug}/customer/login")
        ->assertOk()
        ->assertSee('window.InvoiceShelf.start()', false);

    $this->followingRedirects()->get("/{$companySlug}/customer/forgot-password")
        ->assertOk()
        ->assertSee('window.InvoiceShelf.start()', false);

    $this->followingRedirects()->get("/{$companySlug}/customer/reset/password/example-token")
        ->assertOk()
        ->assertSee('window.InvoiceShelf.start()', false);
});

test('customer can login to the customer portal', function () {
    $customer = Customer::factory()->create([
        'password' => 'secret123',
        'enable_portal' => true,
    ]);

    $response = postJson("/{$customer->company->slug}/customer/login", [
        'email' => $customer->email,
        'password' => 'secret123',
        'device_name' => 'customer-portal-web',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('customer portal login rejects invalid credentials', function () {
    $customer = Customer::factory()->create([
        'password' => 'secret123',
        'enable_portal' => true,
    ]);

    postJson("/{$customer->company->slug}/customer/login", [
        'email' => $customer->email,
        'password' => 'wrong-password',
        'device_name' => 'customer-portal-web',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('customer portal login rejects portal-disabled customers', function () {
    $customer = Customer::factory()->create([
        'password' => 'secret123',
        'enable_portal' => false,
    ]);

    postJson("/{$customer->company->slug}/customer/login", [
        'email' => $customer->email,
        'password' => 'secret123',
        'device_name' => 'customer-portal-web',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
