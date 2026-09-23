<?php

use App\Domains\Accounts\Mail\CompanyInvitationMail;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\Database\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs($user, ['*']);
});

/**
 * The owner role of a company. Bouncer scopes role queries to whichever
 * company it last worked in, so the lookup steps outside that scope.
 */
function invitationOwnerRole(Company $company): Role
{
    return Role::withoutGlobalScopes()->where('name', 'owner')->where('scope', $company->id)->firstOrFail();
}

test('invite user to company', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    $response = postJson('api/v1/company-invitations', [
        'email' => 'newuser@example.com',
        'role_id' => $role->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('company_invitations', [
        'company_id' => $company->id,
        'email' => 'newuser@example.com',
        'role_id' => $role->id,
        'status' => 'pending',
    ]);

    Mail::assertSent(CompanyInvitationMail::class);
});

test('cannot invite user already in company', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);
    $existingUser = User::first();

    $response = postJson('api/v1/company-invitations', [
        'email' => $existingUser->email,
        'role_id' => $role->id,
    ]);

    $response->assertStatus(422);
});

test('cannot send duplicate invitation', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    postJson('api/v1/company-invitations', [
        'email' => 'duplicate@example.com',
        'role_id' => $role->id,
    ])->assertOk();

    postJson('api/v1/company-invitations', [
        'email' => 'duplicate@example.com',
        'role_id' => $role->id,
    ])->assertStatus(422);
});

test('list pending invitations for company', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    postJson('api/v1/company-invitations', [
        'email' => 'invited@example.com',
        'role_id' => $role->id,
    ]);

    $response = getJson('api/v1/company-invitations');

    $response->assertOk();
    $response->assertJsonCount(1, 'invitations');
});

test('cancel pending invitation', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    $storeResponse = postJson('api/v1/company-invitations', [
        'email' => 'cancel@example.com',
        'role_id' => $role->id,
    ]);
    $storeResponse->assertOk();

    $invitation = CompanyInvitation::where('email', 'cancel@example.com')->first();
    $this->assertNotNull($invitation);

    deleteJson("api/v1/company-invitations/{$invitation->id}")
        ->assertOk();

    $this->assertDatabaseMissing('company_invitations', [
        'email' => 'cancel@example.com',
    ]);
});

test('accept invitation adds user to company', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    // Create a new user not in the company
    $newUser = User::factory()->create(['email' => 'accept@example.com']);

    // Create invitation
    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => $newUser->id,
        'email' => $newUser->email,
        'role_id' => $role->id,
        'token' => 'test-accept-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    // Act as the invited user
    Sanctum::actingAs($newUser, ['*']);

    postJson("api/v1/invitations/{$invitation->token}/accept")
        ->assertOk();

    $this->assertTrue($newUser->fresh()->hasCompany($company->id));
    $this->assertDatabaseHas('company_invitations', [
        'token' => 'test-accept-token',
        'status' => 'accepted',
    ]);
});

test('decline invitation', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);
    $newUser = User::factory()->create(['email' => 'decline@example.com']);

    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => $newUser->id,
        'email' => $newUser->email,
        'role_id' => $role->id,
        'token' => 'test-decline-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    Sanctum::actingAs($newUser, ['*']);

    postJson("api/v1/invitations/{$invitation->token}/decline")
        ->assertOk();

    $this->assertDatabaseHas('company_invitations', [
        'token' => 'test-decline-token',
        'status' => 'declined',
    ]);
    $this->assertFalse($newUser->fresh()->hasCompany($company->id));
});

test('cannot accept expired invitation', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);
    $newUser = User::factory()->create(['email' => 'expired@example.com']);

    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => $newUser->id,
        'email' => $newUser->email,
        'role_id' => $role->id,
        'token' => 'test-expired-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($newUser, ['*']);

    postJson("api/v1/invitations/{$invitation->token}/accept")
        ->assertStatus(422);

    $this->assertFalse($newUser->fresh()->hasCompany($company->id));
});

test('bootstrap includes pending invitations', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);
    $user = User::first();

    CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'email' => $user->email,
        'role_id' => $role->id,
        'token' => 'test-bootstrap-token',
        'status' => 'pending',
        'invited_by' => $user->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = getJson('api/v1/bootstrap');

    $response->assertOk();
    $response->assertJsonStructure(['pending_invitations']);
});

test('get invitation details by token', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    // Create invitation for non-existent user
    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => null,
        'email' => 'newperson@example.com',
        'role_id' => $role->id,
        'token' => 'test-details-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = getJson("api/v1/invitations/{$invitation->token}/details");

    $response->assertOk();
    $response->assertJsonPath('email', 'newperson@example.com');
    $response->assertJsonPath('company_name', $company->name);
});

test('register with invitation creates account and accepts', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);

    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => null,
        'email' => 'register@example.com',
        'role_id' => $role->id,
        'token' => 'test-register-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = postJson('api/v1/auth/register-with-invitation', [
        'name' => 'New User',
        'email' => 'register@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invitation_token' => 'test-register-token',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['type', 'token']);

    $newUser = User::where('email', 'register@example.com')->first();
    $this->assertNotNull($newUser);
    $this->assertTrue($newUser->hasCompany($company->id));
    $this->assertDatabaseHas('company_invitations', [
        'token' => 'test-register-token',
        'status' => 'accepted',
    ]);
});

test('cannot register with mismatched email', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);

    CompanyInvitation::create([
        'company_id' => $company->id,
        'user_id' => null,
        'email' => 'correct@example.com',
        'role_id' => $role->id,
        'token' => 'test-mismatch-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = postJson('api/v1/auth/register-with-invitation', [
        'name' => 'Wrong User',
        'email' => 'wrong@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invitation_token' => 'test-mismatch-token',
    ]);

    $response->assertStatus(422);
});

/**
 * A member of the company who is not its owner, able to see customers and no
 * more: enough to be signed in, not enough to manage who belongs.
 */
function invitationMember(Company $company, string $email = 'member@example.com'): User
{
    $member = User::factory()->create(['email' => $email]);
    $member->companies()->attach($company->id);

    return $member;
}

test('a member who is not the owner cannot list, send or cancel invitations', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);
    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'email' => 'pending@example.com',
        'role_id' => $role->id,
        'token' => 'member-cannot-see',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    Sanctum::actingAs(invitationMember($company), ['*']);

    getJson('api/v1/company-invitations')->assertForbidden();
    postJson('api/v1/company-invitations', ['email' => 'escalate@example.com', 'role_id' => $role->id])->assertForbidden();
    deleteJson("api/v1/company-invitations/{$invitation->id}")->assertForbidden();

    $this->assertDatabaseMissing('company_invitations', ['email' => 'escalate@example.com']);
    $this->assertDatabaseHas('company_invitations', ['id' => $invitation->id]);
});

test('an invitation from another company cannot be cancelled', function () {
    $other = Company::factory()->create();
    $invitation = CompanyInvitation::create([
        'company_id' => $other->id,
        'email' => 'elsewhere@example.com',
        'role_id' => invitationOwnerRole(Company::first())->id,
        'token' => 'other-company-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    deleteJson("api/v1/company-invitations/{$invitation->id}")->assertNotFound();

    $this->assertDatabaseHas('company_invitations', ['id' => $invitation->id]);
});

test('an invitation takes a role of the company it is for', function () {
    $other = Company::factory()->create();
    $foreignRole = Role::create(['name' => 'foreign-role', 'title' => 'Foreign', 'scope' => $other->id]);

    postJson('api/v1/company-invitations', [
        'email' => 'foreign-role@example.com',
        'role_id' => $foreignRole->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('role_id');
});

test('sending an invitation does not hand its token back', function () {
    Mail::fake();

    $company = Company::first();
    $role = invitationOwnerRole($company);

    postJson('api/v1/company-invitations', ['email' => 'no-token@example.com', 'role_id' => $role->id])
        ->assertOk()
        ->assertJsonMissingPath('invitation.token');
});

test('only the invited person can accept or decline an invitation', function () {
    $company = Company::first();
    $role = invitationOwnerRole($company);
    $invitation = CompanyInvitation::create([
        'company_id' => $company->id,
        'email' => 'invited@example.com',
        'role_id' => $role->id,
        'token' => 'someone-elses-token',
        'status' => 'pending',
        'invited_by' => User::first()->id,
        'expires_at' => now()->addDays(7),
    ]);

    $intruder = User::factory()->create(['email' => 'intruder@example.com']);
    Sanctum::actingAs($intruder, ['*']);

    postJson("api/v1/invitations/{$invitation->token}/accept")->assertForbidden();
    postJson("api/v1/invitations/{$invitation->token}/decline")->assertForbidden();

    expect($intruder->fresh()->hasCompany($company->id))->toBeFalse();
    $this->assertDatabaseHas('company_invitations', ['id' => $invitation->id, 'status' => 'pending']);
});
