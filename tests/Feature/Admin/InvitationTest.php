<?php

use App\Mail\CompanyInvitationMail;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
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

test('invite user to company', function () {
    Mail::fake();

    $company = Company::first();
    $role = Role::where('name', 'owner')->first();

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
    $role = Role::where('name', 'owner')->first();
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
    $role = Role::where('name', 'owner')->first();

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
    $role = Role::where('name', 'owner')->first();

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
    $role = Role::where('name', 'owner')->first();

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
    $role = Role::where('name', 'owner')->first();

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
    $role = Role::where('name', 'owner')->first();
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
    $role = Role::where('name', 'owner')->first();
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
    $role = Role::where('name', 'owner')->first();
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
