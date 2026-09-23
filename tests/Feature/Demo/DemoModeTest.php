<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Operations\Demo\BlockDemoChanges;
use App\Providers\AppServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::query()->where('email', 'demo@invoiceshelf.com')->firstOrFail();
    $this->company = $this->user->companies()->firstOrFail();
    $this->portalCustomer = Customer::factory()->create(['company_id' => $this->company->id, 'email' => 'customer@invoiceshelf.com']);
    $this->otherCustomer = Customer::factory()->create(['company_id' => $this->company->id]);

    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->user, ['*']);

    config(['app.env' => 'demo']);
});

function demoParameters(string $uri, int $companyId, string $slug): string
{
    return strtr($uri, [
        '{member}' => '1',
        '{role}' => '1',
        '{exchange_rate_provider}' => '1',
        '{slug}' => 'tasks-projects',
        '{user}' => '1',
        '{id}' => '1',
        '{connection}' => '1',
        '{company}' => $slug,
    ]);
}

test('every change the demo refuses is refused with demo_mode', function () {
    foreach (BlockDemoChanges::BLOCKED as $method => $uris) {
        foreach ($uris as $uri) {
            if (str_contains($uri, '/customer/profile')) {
                continue;
            }

            json($method, '/'.demoParameters($uri, $this->company->id, $this->company->slug), [])
                ->assertForbidden()
                ->assertJson(['error' => 'demo_mode'], "{$method} {$uri}");
        }
    }
});

test('the portal customer cannot change the portal profile', function () {
    $this->portalCustomer->forceFill(['enable_portal' => true])->save();

    $this->actingAs($this->portalCustomer, 'customer');

    postJson("/api/v1/{$this->company->slug}/customer/profile", ['name' => 'Hijacked'])
        ->assertForbidden()
        ->assertJson(['error' => 'demo_mode']);
});

test('everything else stays open to try', function () {
    postJson('/api/v1/customers', ['name' => 'Visitor Ltd'])->assertSuccessful();
    putJson("/api/v1/customers/{$this->otherCustomer->id}", ['name' => 'Renamed'])->assertSuccessful();
    postJson('/api/v1/company/settings', ['settings' => ['tax_per_item' => 'YES']])->assertSuccessful();
});

test('the customer whose portal sign-in is offered cannot be changed or removed', function () {
    putJson("/api/v1/customers/{$this->portalCustomer->id}", ['name' => 'Hijacked', 'email' => 'me@evil.test'])
        ->assertForbidden()
        ->assertJson(['error' => 'demo_mode']);
    json('DELETE', "/api/v1/customers/{$this->portalCustomer->id}")->assertForbidden();
    postJson('/api/v1/customers/delete', ['ids' => [$this->otherCustomer->id, $this->portalCustomer->id]])->assertForbidden();

    expect($this->portalCustomer->fresh()->email)->toBe('customer@invoiceshelf.com');
});

test('nothing is refused outside the demo', function () {
    config(['app.env' => 'testing']);

    putJson('/api/v1/me', ['name' => 'Demo User', 'email' => 'demo@invoiceshelf.com'])
        ->assertJsonMissing(['error' => 'demo_mode']);
});

test('the demo does not offer the screens behind what it refuses', function () {
    app()->getProvider(AppServiceProvider::class)->addMenus();
    $demo = getJson('/api/v1/bootstrap')->assertOk()->json();

    config(['app.env' => 'testing']);
    app()->getProvider(AppServiceProvider::class)->addMenus();
    $normal = getJson('/api/v1/bootstrap')->assertOk()->json();

    $links = fn (array $payload) => array_merge(array_column($payload['main_menu'], 'link'), array_column($payload['setting_menu'], 'link'));

    expect($links($demo))->not->toContain('/admin/members', '/admin/settings/roles', '/admin/settings/mail-config')
        ->and($links($normal))->toContain('/admin/members', '/admin/settings/roles', '/admin/settings/mail-config');
});

test('the demo rebuilds itself on its schedule, even while down for maintenance', function () {
    config(['invoiceshelf.demo.reset_cron' => '0 */6 * * *']);
    require base_path('routes/console.php');

    $reset = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'reset:app --force'));

    expect($reset)->not->toBeNull()
        ->and($reset->expression)->toBe('0 */6 * * *')
        ->and($reset->evenInMaintenanceMode)->toBeTrue()
        ->and($reset->withoutOverlapping)->toBeTrue()
        ->and($reset->onOneServer)->toBeTrue();
});

test('the shell and the client manifest tell the SPA about the demo', function () {
    $manifest = getJson('/api/v1/app/client-manifest')->assertOk()->json();

    expect($manifest['demo_mode'])->toBeTrue()
        ->and($manifest['demo'])->toMatchArray([
            'email' => 'demo@invoiceshelf.com',
            'password' => 'demo',
            'portal_email' => 'customer@invoiceshelf.com',
            'portal_path' => "/{$this->company->slug}/customer/login",
        ])
        ->and($manifest['demo']['next_reset_at'])->not->toBeNull();
});

test('the demo seeders need no Faker, which production images do not ship', function () {
    foreach (['PublicDemoSeeder', 'RealisticDemoSeeder'] as $seeder) {
        expect(file_get_contents(database_path("seeders/{$seeder}.php")))
            ->not->toContain('factory(')
            ->not->toContain('fake(')
            ->not->toContain('use Faker');
    }
});
