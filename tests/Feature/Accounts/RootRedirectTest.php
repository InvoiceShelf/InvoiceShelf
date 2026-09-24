<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Operations\Installation\Http\Middleware\EnsureInstalled;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// The `install` middleware's probe is cached per process before the test
// schema exists, so it is checked by name and bypassed for the redirects.
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->withoutMiddleware(EnsureInstalled::class);
});

test('the root still waits for the install and keeps signed-in users away from the sign-in page', function () {
    expect(Route::getRoutes()->getByName('home')->gatherMiddleware())->toContain('install', 'guest');
});

test('a signed-out visitor to the root is sent to the sign-in page', function () {
    $this->get('/')->assertRedirect('/login');
});

test('a signed-in visitor to the root is sent to the dashboard', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->get('/')->assertRedirect('/admin/dashboard');
});

test('a customer portal session does not count as signed in at the root', function () {
    // Not actingAs(): that also makes `customer` the default guard, which a
    // real request never does.
    auth('customer')->setUser(Customer::factory()->create());

    $this->get('/')->assertRedirect('/login');
});
