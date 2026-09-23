<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Operations\Installation\Authentication\InstallWizardAuth;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * The setup wizard signs in with a token whose only ability is the
 * installer's. Nothing on the API checks that ability, so such a token must
 * only ever be accepted on the wizard's own requests of an unfinished
 * install, and must not outlive it.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $this->admin = User::query()->where('role', 'super admin')->firstOrFail();
    $this->company = $this->admin->companies()->firstOrFail();
    $this->wizardToken = $this->admin->createToken(InstallWizardAuth::TOKEN_NAME, [InstallWizardAuth::TOKEN_ABILITY])->plainTextToken;
});

function asBearer(string $token, bool $wizard, int $companyId): array
{
    return array_filter([
        'Authorization' => "Bearer {$token}",
        'company' => (string) $companyId,
        InstallWizardAuth::HEADER => $wizard ? InstallWizardAuth::HEADER_VALUE : null,
    ]);
}

test('a wizard token opens the wizard\'s own requests', function () {
    getJson('/api/v1/me', asBearer($this->wizardToken, true, $this->company->id))->assertOk();
});

test('a wizard token is refused everywhere else', function () {
    getJson('/api/v1/me', asBearer($this->wizardToken, false, $this->company->id))->assertUnauthorized();
    getJson('/api/v1/super-admin/companies', asBearer($this->wizardToken, false, $this->company->id))->assertUnauthorized();
});

test('an ordinary sign-in token is not affected', function () {
    $token = $this->admin->createToken('laptop')->plainTextToken;

    getJson('/api/v1/me', asBearer($token, false, $this->company->id))->assertOk();
});

test('finishing the wizard revokes its tokens', function () {
    postJson('/api/v1/installation/wizard-step', ['profile_complete' => 'COMPLETED'], asBearer($this->wizardToken, true, $this->company->id))
        ->assertOk()
        ->assertJson(['profile_complete' => 'COMPLETED']);

    expect(Setting::getSetting('profile_complete'))->toBe('COMPLETED')
        ->and(PersonalAccessToken::query()->where('name', InstallWizardAuth::TOKEN_NAME)->exists())->toBeFalse();

    getJson('/api/v1/me', asBearer($this->wizardToken, true, $this->company->id))->assertUnauthorized();
});
