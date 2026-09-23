<?php

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Application\MemberService;
use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * Store an access token, its refresh token and an unused authorization code
 * for the user and client, the rows a completed grant leaves behind.
 *
 * @return array{access: string, refresh: string, code: string}
 */
function grantFor(int $userId, string $clientId): array
{
    $ids = ['access' => Str::random(40), 'refresh' => Str::random(40), 'code' => Str::random(40)];

    Passport::token()->forceFill([
        'id' => $ids['access'], 'user_id' => $userId, 'client_id' => $clientId,
        'scopes' => [], 'revoked' => false, 'expires_at' => now()->addHour(),
    ])->save();

    Passport::refreshToken()->forceFill([
        'id' => $ids['refresh'], 'access_token_id' => $ids['access'],
        'revoked' => false, 'expires_at' => now()->addDays(30),
    ])->save();

    Passport::authCode()->forceFill([
        'id' => $ids['code'], 'user_id' => $userId, 'client_id' => $clientId,
        'scopes' => '[]', 'revoked' => false, 'expires_at' => now()->addMinutes(10),
    ])->save();

    return $ids;
}

/**
 * @param  array{access: string, refresh: string, code: string}  $ids
 */
function grantRevoked(array $ids): bool
{
    return Passport::token()->find($ids['access'])->revoked
        && Passport::refreshToken()->find($ids['refresh'])->revoked
        && Passport::authCode()->find($ids['code'])->revoked;
}

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('revoking one client leaves the user\'s other clients alone', function () {
    $user = User::factory()->create();
    $first = grantFor($user->id, (string) Str::uuid());
    $secondClient = (string) Str::uuid();
    $second = grantFor($user->id, $secondClient);

    app(AccessRevoker::class)->revokeClient($user->id, $secondClient);

    expect(grantRevoked($second))->toBeTrue()
        ->and(Passport::token()->find($first['access'])->revoked)->toBeFalse();
});

test('deleting a member revokes every grant they gave', function () {
    Event::fake([UserAccessRevoked::class]);
    $member = User::factory()->create();
    $ids = grantFor($member->id, (string) Str::uuid());

    app(MemberService::class)->delete([$member->id]);

    expect(grantRevoked($ids))->toBeTrue();
    Event::assertDispatched(UserAccessRevoked::class, fn ($event) => $event->userId === $member->id);
});

test('removing a member from a company announces it for that company only', function () {
    Event::fake([CompanyAccessRevoked::class]);
    $first = Company::first();
    $second = Company::factory()->create();
    $member = User::factory()->create();
    $member->companies()->attach([$first->id, $second->id]);

    // The caller manages both companies, so leaving the second off detaches it.
    app(MemberService::class)->update($member, [], [['id' => $first->id, 'role' => 'owner']], [$first->id, $second->id]);

    Event::assertDispatched(CompanyAccessRevoked::class, fn ($event) => $event->userId === $member->id && $event->companyId === $second->id);
    Event::assertDispatchedTimes(CompanyAccessRevoked::class, 1);
});

test('deleting a company announces it for every member', function () {
    Event::fake([CompanyAccessRevoked::class]);
    $company = Company::factory()->create();
    $members = User::factory()->count(2)->create();
    $company->users()->attach($members->pluck('id'));

    app(CompanyService::class)->delete($company);

    foreach ($members as $member) {
        Event::assertDispatched(CompanyAccessRevoked::class, fn ($event) => $event->userId === $member->id && $event->companyId === $company->id);
    }
});

test('revoking everything revokes every stored grant', function () {
    $ids = grantFor(User::factory()->create()->id, (string) Str::uuid());

    app(AccessRevoker::class)->revokeEverything();

    expect(grantRevoked($ids))->toBeTrue();
});
