<?php

use App\Domains\Accounts\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * The reported symptom: a browser sign-in behind a TLS-terminating proxy is
 * answered with a redirect to http://, which the browser refuses, so the SPA
 * reports a network error and only a refresh gets the user in.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
});

test('the sign-in redirect keeps https when the app is told to force it', function () {
    $user = User::factory()->create(['password' => Hash::make('secret1234')]);

    config(['app.force_https' => true]);
    app()->getProvider(AppServiceProvider::class)->bootHttps();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret1234',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://');
});
