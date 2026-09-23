<?php

// Pilot behavioural suite — settings store semantics, cron webhook, admin dashboard.
// Spec: platform-operations-spec.md §1, §5, §6.

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
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

it('upserts settings by key and reads them back', function () {
    postJson('/api/v1/settings', ['settings' => ['login_page_heading' => 'v1', 'copyright_text' => 'v2']])
        ->assertOk()
        ->assertJson(['success' => true]);

    postJson('/api/v1/settings', ['settings' => ['login_page_heading' => 'v3']])->assertOk();

    expect(DB::table('settings')->where('option', 'login_page_heading')->count())->toBe(1);
    expect(DB::table('settings')->where('option', 'login_page_heading')->value('value'))->toBe('v3');

    getJson('/api/v1/settings?key=login_page_heading')->assertOk()->assertJson(['login_page_heading' => 'v3']);
});

it('reads a missing setting as null', function () {
    DB::table('settings')->where('option', 'copyright_text')->delete();

    getJson('/api/v1/settings?key=copyright_text')
        ->assertOk()
        ->assertJson(['copyright_text' => null]);
});

it('validates the settings endpoints', function () {
    postJson('/api/v1/settings', [])->assertStatus(422)->assertJsonValidationErrors(['settings']);
    getJson('/api/v1/settings')->assertStatus(422);
});

it('reads and writes only the settings the shell paints with', function () {
    DB::table('settings')->updateOrInsert(['option' => 'mail_password'], ['value' => 'stored-password']);
    $installed = DB::table('settings')->where('option', 'profile_complete')->value('value');

    foreach ([
        ['profile_complete' => '0'],
        ['gotenberg_host' => 'http://169.254.169.254'],
        ['media_disk_id' => '999'],
        ['mail_password' => 'replaced'],
        ['show_sidebar_group_labels' => 'YES', 'updater_channel' => 'insider'],
    ] as $settings) {
        postJson('/api/v1/settings', ['settings' => $settings])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    expect(DB::table('settings')->where('option', 'profile_complete')->value('value'))->toBe($installed)
        ->and(DB::table('settings')->where('option', 'gotenberg_host')->exists())->toBeFalse()
        ->and(DB::table('settings')->where('option', 'mail_password')->value('value'))->toBe('stored-password')
        ->and(DB::table('settings')->where('option', 'updater_channel')->exists())->toBeFalse();

    getJson('/api/v1/settings?key=mail_password')
        ->assertStatus(422)
        ->assertDontSee('stored-password');

    postJson('/api/v1/settings', ['settings' => ['show_sidebar_group_labels' => 'NO', 'save_pdf_to_disk' => 'YES']])
        ->assertOk();

    expect(DB::table('settings')->where('option', 'show_sidebar_group_labels')->value('value'))->toBe('NO');
});

it('guards the cron webhook with the shared token', function () {
    config(['services.cron_job.auth_token' => 'pilot-cron-token']);

    getJson('/api/cron')->assertUnauthorized();

    $this->withHeaders(['x-authorization-token' => 'wrong'])
        ->getJson('/api/cron')->assertUnauthorized();

    $this->withHeaders(['x-authorization-token' => 'pilot-cron-token'])
        ->getJson('/api/cron')->assertOk()->assertJson(['success' => true]);
});

it('reports versions and row counts on the admin dashboard', function () {
    $payload = getJson('/api/v1/super-admin/dashboard')->assertOk()->json();

    $expected = preg_replace('~[\r\n]+~', '', file_get_contents(base_path('version.md')));
    expect($payload['app_version'])->toBe($expected);
    expect($payload['php_version'])->toBe(phpversion());
    expect($payload['database']['driver'])->toBe(config('database.default'));
    expect($payload['counts']['companies'])->toBe(DB::table('companies')->count());
    expect($payload['counts']['users'])->toBe(DB::table('users')->count());
});
