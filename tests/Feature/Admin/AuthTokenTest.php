<?php

use App\Domains\Accounts\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * The bearer-token door, from the outside.
 *
 * Tokens minted by AuthController never expire, so the account that holds them
 * has to be able to see its own devices and cut one off; and the door itself
 * has to cost something to knock on, or the tokens are only as good as the
 * weakest password on the instance.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $this->owner->companies()->first()->id]);
});

test('the token list carries only the caller own devices and marks the one in use', function () {
    $phone = $this->owner->createToken('phone');
    $this->owner->createToken('laptop');
    User::factory()->create()->createToken('somebody else');

    $tokens = collect(
        $this->withHeader('Authorization', 'Bearer '.$phone->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->json('data')
    );

    expect($tokens->pluck('name')->all())->toEqualCanonicalizing(['phone', 'laptop'])
        ->and($tokens->firstWhere('name', 'phone')['current'])->toBeTrue()
        ->and($tokens->firstWhere('name', 'laptop')['current'])->toBeFalse()
        ->and(array_keys($tokens->first()))->toEqualCanonicalizing([
            'id', 'name', 'last_used_at', 'created_at', 'current',
        ]);
});

test('revoking a token turns the device away on its next request', function () {
    $phone = $this->owner->createToken('phone');

    $this->withHeader('Authorization', 'Bearer '.$phone->plainTextToken)
        ->deleteJson('/api/v1/auth/tokens/'.$phone->accessToken->id)
        ->assertNoContent();

    // The guard holds on to whoever it resolved for the previous request.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$phone->plainTextToken)
        ->getJson('/api/v1/auth/check')
        ->assertUnauthorized();
});

test('another account token is answered as if it did not exist', function () {
    $mine = $this->owner->createToken('phone');
    $theirs = User::factory()->create()->createToken('somebody else');

    $this->withHeader('Authorization', 'Bearer '.$mine->plainTextToken)
        ->deleteJson('/api/v1/auth/tokens/'.$theirs->accessToken->id)
        ->assertNotFound();

    expect($theirs->accessToken->fresh())->not->toBeNull();
});

test('a caller holding no personal access token has nothing marked current', function () {
    $this->owner->createToken('phone');

    // Sanctum hands a session-authenticated caller a transient token, which is
    // what the admin SPA signs in with.
    Sanctum::actingAs($this->owner, ['*']);

    $tokens = getJson('/api/v1/auth/tokens')->assertOk()->json('data');

    expect($tokens)->toHaveCount(1)
        ->and($tokens[0]['current'])->toBeFalse();
});

test('the eleventh sign-in attempt within a minute is refused', function () {
    $credentials = [
        'username' => 'demo@invoiceshelf.com',
        'password' => 'not-the-password',
        'device_name' => 'probe',
    ];

    foreach (range(1, 10) as $ignored) {
        postJson('/api/v1/auth/login', $credentials)->assertStatus(422);
    }

    postJson('/api/v1/auth/login', $credentials)->assertStatus(429);
});
