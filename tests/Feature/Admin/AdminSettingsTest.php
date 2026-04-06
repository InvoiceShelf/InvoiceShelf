<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('company settings config uses canonical v2 links', function () {
    $links = collect(config('invoiceshelf.setting_menu'))->pluck('link');

    expect($links)->toContain('/admin/settings/exchange-rate');
    expect($links)->toContain('/admin/settings/payment-modes');
    expect($links)->toContain('/admin/settings/expense-categories');
    expect($links)->toContain('/admin/settings/roles');
    expect($links)->toContain('/admin/settings/mail-config');

    expect($links)->not->toContain('/admin/settings/roles-settings');
    expect($links)->not->toContain('/admin/settings/exchange-rate-provider');
    expect($links)->not->toContain('/admin/settings/payment-mode');
    expect($links)->not->toContain('/admin/settings/expense-category');
    expect($links)->not->toContain('/admin/settings/mail-configuration');

    $bootstrapMenu = json_encode(getJson('/api/v1/bootstrap')
        ->assertOk()
        ->json('setting_menu'));

    expect($bootstrapMenu)->not->toContain('roles-settings');
    expect($bootstrapMenu)->not->toContain('exchange-rate-provider');
    expect($bootstrapMenu)->not->toContain('payment-mode');
    expect($bootstrapMenu)->not->toContain('expense-category');
    expect($bootstrapMenu)->not->toContain('mail-configuration');
});

test('super admin bootstrap uses administration mode when requested', function () {
    getJson('/api/v1/bootstrap?admin_mode=1')
        ->assertOk()
        ->assertJsonPath('admin_mode', true)
        ->assertJsonPath('current_company', null)
        ->assertJsonCount(0, 'setting_menu');
});

test('bootstrap without administration mode hydrates the selected company', function () {
    $companyId = User::find(1)->companies()->first()->id;

    getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('current_company.id', $companyId);
});

test('get global mail configuration', function () {
    getJson('/api/v1/mail/config')
        ->assertOk()
        ->assertJsonStructure([
            'mail_driver',
            'from_name',
            'from_mail',
        ]);
});

test('save global mail configuration', function () {
    postJson('/api/v1/mail/config', [
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_username' => 'demo-user',
        'mail_password' => 'secret',
        'mail_encryption' => 'tls',
        'from_name' => 'InvoiceShelf',
        'from_mail' => 'hello@example.com',
    ])
        ->assertOk()
        ->assertJson([
            'success' => 'mail_variables_save_successfully',
        ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'mail_driver',
        'value' => 'smtp',
    ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'from_mail',
        'value' => 'hello@example.com',
    ]);
});

test('get pdf configuration', function () {
    getJson('/api/v1/pdf/config')
        ->assertOk()
        ->assertJsonStructure([
            'pdf_driver',
            'gotenberg_host',
            'gotenberg_margins',
            'gotenberg_papersize',
        ]);
});

test('save pdf configuration', function () {
    postJson('/api/v1/pdf/config', [
        'pdf_driver' => 'dompdf',
    ])
        ->assertOk()
        ->assertJson([
            'success' => 'pdf_variables_save_successfully',
        ]);

    $this->assertDatabaseHas('settings', [
        'option' => 'pdf_driver',
        'value' => 'dompdf',
    ]);
});

test('get app version', function () {
    getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJsonStructure([
            'version',
            'channel',
        ]);
});
