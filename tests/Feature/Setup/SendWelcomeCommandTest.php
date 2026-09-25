<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Notifications\MailResetPasswordNotification;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/*
 * `php artisan invoiceshelf:send-welcome`: the handover mail InvoiceShelf
 * Cloud's agent sends after a headless install with a random password.
 */

beforeEach(function () {
    Notification::fake();
    Artisan::call('invoiceshelf:install', [
        '--admin-email' => 'owner@acme.test',
        '--admin-password-random' => true,
        '--company' => 'Acme GmbH',
        '--currency' => 'eur',
        '--timezone' => 'Europe/Berlin',
        '--language' => 'en',
    ]);
});

test('it sends the owner a link to set their password', function () {
    expect(Artisan::call('invoiceshelf:send-welcome', ['--email' => 'owner@acme.test']))->toBe(0);

    Notification::assertSentTo(User::query()->sole(), MailResetPasswordNotification::class);
});

test('an unknown email fails without sending anything', function () {
    expect(Artisan::call('invoiceshelf:send-welcome', ['--email' => 'stranger@acme.test']))->toBe(1);

    Notification::assertNothingSent();
});

test('a throttled second request fails so the caller can report it', function () {
    Artisan::call('invoiceshelf:send-welcome', ['--email' => 'owner@acme.test']);

    expect(Artisan::call('invoiceshelf:send-welcome', ['--email' => 'owner@acme.test']))->toBe(1);
});

test('it refuses before the installation is complete', function () {
    Setting::setSetting('profile_complete', 'IN_PROGRESS');

    expect(Artisan::call('invoiceshelf:send-welcome', ['--email' => 'owner@acme.test']))->toBe(1);
});
