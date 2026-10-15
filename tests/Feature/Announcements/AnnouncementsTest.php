<?php

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Announcements\Application\AnnouncementService;
use App\Platform\Announcements\Models\Announcement;
use App\Platform\Announcements\Models\AnnouncementDismissal;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->admin = User::query()->where('role', 'super admin')->firstOrFail();
    $this->company = $this->admin->companies()->firstOrFail();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->admin, ['*']);
});

afterEach(fn () => Carbon::setTestNow());

function member(Company $company): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($company->id);

    return $user;
}

function localAnnouncement(array $overrides = []): Announcement
{
    return Announcement::query()->create([
        'source' => Announcement::SOURCE_LOCAL,
        'title' => 'Maintenance tonight',
        'body' => 'The app is offline from 22:00 to 23:00.',
        'level' => 'warning',
        'audience' => 'everyone',
        ...$overrides,
    ]);
}

function feedResponse(array $announcements): array
{
    return ['success' => true, 'announcements' => $announcements];
}

function feedItem(string $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'level' => 'info',
        'audience' => 'everyone',
        'title' => 'InvoiceShelf 3.1 is out',
        'body' => 'Payment reminders and more.',
        'link_url' => 'https://invoiceshelf.com/blog/3-1',
        'link_label' => 'Read more',
        'starts_at' => null,
        'ends_at' => null,
        'updated_at' => '2026-09-29T10:00:00+00:00',
        'translations' => ['de' => ['title' => 'InvoiceShelf 3.1 ist da']],
        ...$overrides,
    ];
}

test('a super admin writes announcements, validated, and a member cannot', function () {
    $this->postJson('/api/v1/super-admin/announcements', [
        'title' => 'Welcome',
        'body' => 'Say hello.',
        'level' => 'info',
        'audience' => 'everyone',
        'link_url' => 'https://example.com',
        'link_label' => 'Open',
        'translations' => ['de' => ['title' => 'Willkommen', 'body' => '']],
    ])->assertCreated()
        ->assertJsonPath('data.source', 'local')
        ->assertJsonPath('data.translations', ['de' => ['title' => 'Willkommen']]);

    $this->postJson('/api/v1/super-admin/announcements', [
        'title' => 'Bad',
        'body' => 'x',
        'level' => 'loud',
        'audience' => 'everyone',
        'link_url' => 'http://insecure.example',
        'translations' => ['xx' => ['title' => 'Nope']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['level', 'link_url', 'link_label', 'translations']);

    Sanctum::actingAs(member($this->company), ['*']);
    $this->getJson('/api/v1/super-admin/announcements')->assertForbidden();
});

test('announcements from the feed can only be hidden', function () {
    $fromFeed = Announcement::query()->create(['source' => 'feed', 'external_id' => 'abc', 'title' => 'From feed', 'body' => 'x']);
    $payload = ['title' => 'Changed', 'body' => 'x', 'level' => 'info', 'audience' => 'everyone'];

    $this->putJson("/api/v1/super-admin/announcements/{$fromFeed->id}", $payload)->assertUnprocessable();
    $this->deleteJson("/api/v1/super-admin/announcements/{$fromFeed->id}")->assertUnprocessable();
    $this->patchJson("/api/v1/super-admin/announcements/{$fromFeed->id}/visibility", ['hidden' => true])
        ->assertOk()->assertJsonPath('data.hidden', true);

    expect(app(AnnouncementService::class)->activeFor($this->admin, $this->company->id))->toBe([]);
});

test('each person sees the live announcements meant for them, in their language', function () {
    Carbon::setTestNow('2026-09-29 12:00:00');
    localAnnouncement(['title' => 'Everyone', 'translations' => ['de' => ['title' => 'Alle']]]);
    localAnnouncement(['title' => 'Owners only', 'audience' => 'admins', 'level' => 'critical']);
    localAnnouncement(['title' => 'Not yet', 'starts_at' => '2026-09-30 00:00:00']);
    localAnnouncement(['title' => 'Over', 'ends_at' => '2026-09-29 11:00:00']);
    $member = member($this->company);
    $member->setSettings(['language' => 'de']);
    $service = app(AnnouncementService::class);

    expect(array_column($service->activeFor($member, $this->company->id), 'title'))->toBe(['Alle'])
        ->and(array_column($service->activeFor($this->admin, $this->company->id), 'title'))->toBe(['Owners only', 'Everyone']);

    $this->company->forceFill(['owner_id' => $member->id])->save();
    expect(array_column($service->activeFor($member, $this->company->id), 'title'))->toBe(['Owners only', 'Alle']);
});

test('a dismissed announcement stays away until it is changed', function () {
    $announcement = localAnnouncement();
    $member = member($this->company);
    Sanctum::actingAs($member, ['*']);

    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('announcements.0.id', $announcement->id);
    $this->postJson("/api/v1/announcements/{$announcement->id}/dismiss")->assertOk();
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('announcements', []);

    $this->travel(1)->minutes();
    $announcement->update(['body' => 'Now from 22:30.']);
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('announcements.0.body', 'Now from 22:30.');
});

test('the feed is copied, updated, and what leaves it is removed, keeping local choices', function () {
    Setting::setSetting('version', '3.1.0');
    Setting::setSetting('updater_channel', 'insider');
    Http::fake(['*/api/announcements*' => Http::sequence()
        ->push(feedResponse([feedItem('one'), feedItem('two', ['title' => 'Second', 'level' => 'bogus', 'link_url' => 'javascript:alert(1)'])]))
        ->push(feedResponse([feedItem('one', ['title' => 'InvoiceShelf 3.1.1 is out'])])),
    ]);

    $this->artisan('announcements:sync')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'version=3.1.0') && str_contains($request->url(), 'channel=insider'));
    $two = Announcement::query()->where('external_id', 'two')->sole();
    expect(Announcement::query()->where('source', 'feed')->count())->toBe(2)
        ->and($two->level)->toBe('info')
        ->and($two->link_url)->toBeNull();

    $one = Announcement::query()->where('external_id', 'one')->sole();
    app(AnnouncementService::class)->setHidden($one, true);
    localAnnouncement();

    $this->artisan('announcements:sync')->assertSuccessful();

    expect(Announcement::query()->where('source', 'feed')->pluck('external_id')->all())->toBe(['one'])
        ->and($one->fresh()->title)->toBe('InvoiceShelf 3.1.1 is out')
        ->and($one->fresh()->hidden_at)->not->toBeNull()
        ->and(Announcement::query()->where('source', 'local')->count())->toBe(1);
});

test('an unreachable or strange feed changes nothing, and the feed can be switched off', function () {
    Announcement::query()->create(['source' => 'feed', 'external_id' => 'kept', 'title' => 'Kept', 'body' => 'x']);

    Http::fake(['*' => fn () => throw new ConnectionException('down')]);
    $this->artisan('announcements:sync')->assertSuccessful();
    Http::fake(['*' => Http::response(['success' => false], 500)]);
    $this->artisan('announcements:sync')->assertSuccessful();
    expect(Announcement::query()->where('external_id', 'kept')->exists())->toBeTrue();

    config(['invoiceshelf.announcements.feed' => false]);
    Http::fake();
    $this->artisan('announcements:sync')->assertSuccessful();
    Http::assertNothingSent();
});

test('a deleted account takes its dismissals with it', function () {
    $announcement = localAnnouncement();
    $member = member($this->company);
    app(AnnouncementService::class)->dismiss($member, $announcement);

    app(AccessRevoker::class)->revokeUser($member->id);

    expect(AnnouncementDismissal::query()->count())->toBe(0);
});
