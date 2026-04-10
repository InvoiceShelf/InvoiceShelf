<?php

use App\Models\CompanySetting;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

/**
 * Integration test that exercises the real Modules/HelloWorld module end-to-end
 * — no Registry mocking. Proves that when an active module's ServiceProvider
 * registers menu + settings via InvoiceShelf\Modules\Registry, the host app's
 * company-modules index and settings controllers surface it consistently.
 *
 * The HelloWorld module's provider boots automatically because nwidart sees
 * it in `storage/app/modules_statuses.json` (set to enabled when the module
 * was generated via `php artisan module:make HelloWorld`).
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders([
        'company' => $this->companyId,
    ]);
    Sanctum::actingAs($user, ['*']);

    // Mark the module as activated at the InvoiceShelf instance level so it
    // shows up in the company-context Active Modules index.
    Module::query()->updateOrCreate(
        ['name' => 'HelloWorld'],
        ['version' => '1.0.0', 'installed' => true, 'enabled' => true],
    );
});

test('bootstrap merges HelloWorld into main_menu under modules group', function () {
    $response = getJson('api/v1/bootstrap')->assertOk();

    $mainMenu = collect($response->json('main_menu'));
    $helloWorld = $mainMenu->firstWhere('name', 'module-hello-world');

    expect($helloWorld)->not->toBeNull();
    expect($helloWorld['link'])->toBe('/admin/modules/hello-world/dashboard');
    expect($helloWorld['icon'])->toBe('HandRaisedIcon');
    expect($helloWorld['group'])->toBe('modules');
});

test('HelloWorld appears in the company Active Modules index with translated display name', function () {
    $response = getJson('api/v1/company-modules')->assertOk();

    // The DB row stores PascalCase but the controller normalizes to kebab-case
    // for the URL/registry slug.
    $row = collect($response->json('data'))->firstWhere('slug', 'hello-world');
    expect($row)->not->toBeNull();
    expect($row['name'])->toBe('HelloWorld');
    expect($row['display_name'])->toBe('Hello World');
    expect($row['has_settings'])->toBeTrue();
    expect($row['menu']['title'])->toBe('Hello World');
    expect($row['menu']['icon'])->toBe('HandRaisedIcon');
});

test('GET module settings returns the translated HelloWorld schema with defaults', function () {
    $response = getJson('api/v1/modules/hello-world/settings')->assertOk();

    $sections = $response->json('schema.sections');
    expect($sections)->toHaveCount(2);
    expect($sections[0]['title'])->toBe('Greeting');

    $fields = collect($sections[0]['fields'])->keyBy('key');
    expect($fields)->toHaveKeys(['greeting', 'recipient', 'show_emoji']);
    expect($fields['greeting']['type'])->toBe('text');
    expect($fields['greeting']['label'])->toBe('Greeting message');
    expect($fields['greeting']['rules'])->toContain('required');

    // Defaults flow through when nothing has been saved yet
    $values = $response->json('values');
    expect($values['greeting'])->toBe('Hello, world!');
    expect($values['show_emoji'])->toBeTrue();
});

test('PUT module settings persists values per company', function () {
    putJson('api/v1/modules/hello-world/settings', [
        'greeting' => 'Bonjour!',
        'recipient' => 'Marie',
        'show_emoji' => false,
        'tone' => 'formal',
        'note' => 'A custom welcome.',
    ])->assertOk();

    expect(CompanySetting::getSetting('module.hello-world.greeting', $this->companyId))
        ->toBe('Bonjour!');
    expect(CompanySetting::getSetting('module.hello-world.show_emoji', $this->companyId))
        ->toBe('0');
    expect(CompanySetting::getSetting('module.hello-world.tone', $this->companyId))
        ->toBe('formal');

    // Re-fetch and confirm the values round-trip through the show endpoint
    $response = getJson('api/v1/modules/hello-world/settings')->assertOk();
    expect($response->json('values.greeting'))->toBe('Bonjour!');
    expect($response->json('values.tone'))->toBe('formal');
});

test('PUT rejects when required fields are missing', function () {
    putJson('api/v1/modules/hello-world/settings', [
        'recipient' => 'No greeting given',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['greeting', 'tone']);
});
