<?php

use App\Models\Setting;
use App\Models\User;
use App\Providers\AppConfigProvider;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('a sendmail command stored in the settings is never run', function () {
    $configured = config('mail.mailers.sendmail.path');

    Setting::setSettings([
        'mail_driver' => 'sendmail',
        'mail_sendmail_path' => 'touch /tmp/pwned; /usr/sbin/sendmail -bs -i',
    ]);

    // boot() skips everything until the installer has marked the database
    // as created, so run the mail step on its own.
    (new ReflectionMethod(AppConfigProvider::class, 'configureMailFromDatabase'))->invoke(new AppConfigProvider(app()));

    expect(config('mail.default'))->toBe('sendmail')
        ->and(config('mail.mailers.sendmail.path'))->toBe($configured);
});

test('a sendmail path is neither saved nor sent back', function () {
    postJson('/api/v1/mail/config', [
        'mail_driver' => 'sendmail',
        'mail_sendmail_path' => 'id > /tmp/pwned',
        'from_name' => 'InvoiceShelf',
        'from_mail' => 'hello@example.com',
    ])->assertOk();

    expect(Setting::getSetting('mail_sendmail_path'))->toBeNull();

    getJson('/api/v1/mail/config')->assertOk()->assertJsonMissingPath('mail_sendmail_path');
});

test('upgrading deletes the sendmail command already stored', function () {
    Setting::setSettings(['mail_sendmail_path' => 'id > /tmp/pwned']);

    (require database_path('migrations/2026_09_23_110000_forget_stored_sendmail_paths.php'))->up();

    expect(Setting::getSetting('mail_sendmail_path'))->toBeNull();
});
