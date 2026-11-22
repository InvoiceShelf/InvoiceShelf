<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Notifications\Application\NotificationPreferences;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::query()->findOrFail(1);
    $this->companyId = (int) $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * A notice stored straight into the table, as the bell reads it.
 */
function storedNotice(User $user, ?int $companyId, array $overrides = []): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'invoice_viewed',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'company_id' => $companyId,
        'data' => [
            'title' => 'inbox.types.invoice_viewed.title',
            'body' => 'inbox.types.invoice_viewed.body',
            'params' => ['customer' => 'Acme', 'number' => 'INV-000001'],
            'translate' => [],
            'url' => '/admin/invoices/1/view',
        ],
        ...$overrides,
    ]);
}

test('the inbox lists the caller\'s notices for this company and platform notices, newest first', function () {
    $other = Company::factory()->create();
    $this->user->companies()->attach($other->id);
    $old = storedNotice($this->user, $this->companyId, ['created_at' => now()->subDay()]);
    $new = storedNotice($this->user, $this->companyId);
    $platform = storedNotice($this->user, null, ['created_at' => now()->subDays(2)]);
    storedNotice($this->user, (int) $other->id);
    storedNotice(User::factory()->create(), $this->companyId);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.unread_count', 3)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.0.title', 'inbox.types.invoice_viewed.title')
        ->assertJsonPath('data.0.group', 'sales')
        ->assertJsonPath('data.0.params.number', 'INV-000001')
        ->assertJsonPath('data.0.url', '/admin/invoices/1/view')
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.2.id', $platform->id);

    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('unread_count', 3);
});

test('notices can be read, unread, all read and deleted', function () {
    $first = storedNotice($this->user, $this->companyId);
    storedNotice($this->user, $this->companyId);

    $this->postJson("/api/v1/notifications/{$first->id}/read")->assertOk()->assertJsonPath('data.id', $first->id);
    expect($first->fresh()->read_at)->not->toBeNull();
    $this->getJson('/api/v1/notifications?unread=1')->assertJsonPath('meta.total', 1)->assertJsonPath('meta.unread_count', 1);

    $this->postJson("/api/v1/notifications/{$first->id}/unread")->assertOk();
    expect($first->fresh()->read_at)->toBeNull();

    $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
    $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('unread_count', 0);

    $this->deleteJson("/api/v1/notifications/{$first->id}")->assertNoContent();
    expect(DatabaseNotification::query()->whereKey($first->id)->exists())->toBeFalse();
});

test('someone else\'s notice, or another company\'s, is not found', function () {
    $theirs = storedNotice(User::factory()->create(), $this->companyId);
    $other = Company::factory()->create();
    $this->user->companies()->attach($other->id);
    $elsewhere = storedNotice($this->user, (int) $other->id);

    foreach ([$theirs, $elsewhere] as $notice) {
        $this->postJson("/api/v1/notifications/{$notice->id}/read")->assertNotFound();
        $this->deleteJson("/api/v1/notifications/{$notice->id}")->assertNotFound();
    }

    $this->postJson('/api/v1/notifications/read-all')->assertOk();
    expect($theirs->fresh()->read_at)->toBeNull()
        ->and($elsewhere->fresh()->read_at)->toBeNull();
});

test('the inbox needs a signed-in user', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/notifications', ['Authorization' => ''])->assertUnauthorized();
});

test('preferences list every type with its defaults and save the changes', function () {
    $this->getJson('/api/v1/me/notification-preferences')
        ->assertOk()
        ->assertJsonPath('data.0', ['type' => 'invoice_viewed', 'group' => 'sales', 'personal' => false, 'enabled' => true, 'bell' => true, 'mail' => false, 'customised' => ['bell' => false, 'mail' => false]])
        ->assertJsonFragment(['type' => 'recurring_invoice_failed', 'group' => 'recurring', 'personal' => true, 'enabled' => true, 'bell' => true, 'mail' => true]);

    $this->putJson('/api/v1/me/notification-preferences', [
        'preferences' => ['invoice_viewed' => ['mail' => true], 'recurring_invoice_failed' => ['mail' => false, 'bell' => true]],
    ])->assertOk()->assertJsonPath('data.0.mail', true);

    $saved = app(NotificationPreferences::class)->for($this->user->fresh(), $this->companyId);
    expect($saved['invoice_viewed'])->toMatchArray(['bell' => true, 'mail' => true])
        ->and($saved['recurring_invoice_failed'])->toMatchArray(['bell' => true, 'mail' => false])
        ->and($saved['estimate_viewed'])->toMatchArray(['bell' => true, 'mail' => false]);
});

test('preferences refuse unknown types and channels', function () {
    $this->putJson('/api/v1/me/notification-preferences', ['preferences' => ['made_up' => ['mail' => true]]])
        ->assertUnprocessable();
    $this->putJson('/api/v1/me/notification-preferences', ['preferences' => ['invoice_viewed' => ['sms' => true]]])
        ->assertUnprocessable();
    $this->putJson('/api/v1/me/notification-preferences', ['preferences' => ['invoice_viewed' => ['mail' => 'loud']]])
        ->assertUnprocessable();
});
