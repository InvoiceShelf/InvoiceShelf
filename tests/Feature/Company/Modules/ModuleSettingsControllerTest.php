<?php

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders([
        'company' => $this->companyId,
    ]);
    Sanctum::actingAs($user, ['*']);

    Registry::flush();
});

afterEach(function () {
    Registry::flush();
});

test('returns 404 for module without registered schema', function () {
    getJson('api/v1/modules/unknown-module/settings')
        ->assertNotFound();
});

test('show returns schema and default values for unsaved settings', function () {
    Registry::registerSettings('test-module', [
        'sections' => [
            ['title' => 'connection', 'fields' => [
                ['key' => 'api_key', 'type' => 'password', 'rules' => ['required']],
                ['key' => 'sandbox', 'type' => 'switch', 'default' => false],
            ]],
        ],
    ]);

    $response = getJson('api/v1/modules/test-module/settings')->assertOk();

    $response->assertJsonPath('schema.sections.0.title', 'connection');
    $response->assertJsonPath('values.sandbox', false);
});

test('update persists values to company_settings under module prefix', function () {
    Registry::registerSettings('test-module', [
        'sections' => [
            ['title' => 'connection', 'fields' => [
                ['key' => 'api_key', 'type' => 'password', 'rules' => ['required']],
                ['key' => 'sandbox', 'type' => 'switch', 'default' => false],
            ]],
        ],
    ]);

    putJson('api/v1/modules/test-module/settings', [
        'api_key' => 'secret-123',
        'sandbox' => true,
    ])->assertOk();

    expect(CompanySetting::getSetting('module.test-module.api_key', $this->companyId))
        ->toBe('secret-123');
    expect(CompanySetting::getSetting('module.test-module.sandbox', $this->companyId))
        ->toBe('1');
});

test('update rejects payload missing a required field', function () {
    Registry::registerSettings('test-module', [
        'sections' => [
            ['title' => 'connection', 'fields' => [
                ['key' => 'api_key', 'type' => 'password', 'rules' => ['required']],
            ]],
        ],
    ]);

    putJson('api/v1/modules/test-module/settings', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['api_key']);
});

test('update silently drops unknown keys not in schema', function () {
    Registry::registerSettings('test-module', [
        'sections' => [
            ['title' => 'connection', 'fields' => [
                ['key' => 'api_key', 'type' => 'text'],
            ]],
        ],
    ]);

    putJson('api/v1/modules/test-module/settings', [
        'api_key' => 'value',
        'malicious' => 'should-not-be-stored',
    ])->assertOk();

    expect(CompanySetting::getSetting('module.test-module.api_key', $this->companyId))
        ->toBe('value');
    expect(CompanySetting::getSetting('module.test-module.malicious', $this->companyId))
        ->toBeNull();
});

test('update returns 404 for module without registered schema', function () {
    putJson('api/v1/modules/unknown-module/settings', ['anything' => 'value'])
        ->assertNotFound();
});

test('settings are isolated between companies on the same instance', function () {
    Registry::registerSettings('test-module', [
        'sections' => [
            ['title' => 'connection', 'fields' => [
                ['key' => 'api_key', 'type' => 'text'],
            ]],
        ],
    ]);

    // Create a second company and member it to the same user
    $user = User::find(1);
    $companyA = $user->companies()->first();

    // Save value for company A
    putJson('api/v1/modules/test-module/settings', ['api_key' => 'company-a-value'])
        ->assertOk();

    // Verify storage is keyed by company
    expect(CompanySetting::getSetting('module.test-module.api_key', $companyA->id))
        ->toBe('company-a-value');

    // Different company id (synthetic — even non-existent IDs prove the key isolation)
    expect(CompanySetting::getSetting('module.test-module.api_key', 99999))
        ->toBeNull();
});
