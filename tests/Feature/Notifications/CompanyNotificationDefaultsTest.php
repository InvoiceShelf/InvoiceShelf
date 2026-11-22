<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Events\InvoiceViewed;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Notifications\Application\CompanyNotificationDefaults;
use App\Platform\Notifications\Application\NotificationPreferences;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::query()->findOrFail(1);
    $this->companyId = (int) $this->owner->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);
});

function defaultsMember(int $companyId, string $role = 'owner'): User
{
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($companyId);
    BouncerFacade::scope()->onceTo($companyId, fn () => $member->assign($role));
    BouncerFacade::refresh();

    return $member;
}

test('a company default applies until the person chooses for themselves', function () {
    $member = defaultsMember($this->companyId);
    $preferences = app(NotificationPreferences::class);

    app(CompanyNotificationDefaults::class)->update($this->companyId, ['invoice_viewed' => ['mail' => true]]);
    expect($preferences->channels($member, 'invoice_viewed', $this->companyId))->toBe(['database', 'mail']);

    $preferences->update($member, ['invoice_viewed' => ['mail' => false]]);
    expect($preferences->channels($member, 'invoice_viewed', $this->companyId))->toBe(['database']);

    // Going back to the company default.
    $preferences->update($member, ['invoice_viewed' => ['mail' => null]]);
    expect($preferences->channels($member, 'invoice_viewed', $this->companyId))->toBe(['database', 'mail'])
        ->and($preferences->for($member, $this->companyId)['invoice_viewed']['customised'])->toBe(['bell' => false, 'mail' => false]);
});

test('a type switched off for the company reaches nobody there, the shared mailbox included', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    CompanySetting::setSettings(['notify_invoice_viewed' => 'YES', 'notification_email' => 'office@example.com'], $this->companyId);
    app(NotificationPreferences::class)->update($this->owner, ['invoice_viewed' => ['bell' => true, 'mail' => true]]);

    app(CompanyNotificationDefaults::class)->update($this->companyId, ['invoice_viewed' => ['enabled' => false]]);
    InvoiceViewed::dispatch($invoice->id, $this->companyId);

    Notification::assertNothingSent();
});

test('each company has its own defaults', function () {
    $other = Company::factory()->create();
    $this->owner->companies()->attach($other->id);
    app(CompanyNotificationDefaults::class)->update($this->companyId, ['invoice_paid' => ['enabled' => false]]);

    $preferences = app(NotificationPreferences::class);

    expect($preferences->channels($this->owner, 'invoice_paid', $this->companyId))->toBe([])
        ->and($preferences->channels($this->owner, 'invoice_paid', (int) $other->id))->toBe(['database']);
});

test('the owner reads and changes the defaults through the API', function () {
    $this->getJson('/api/v1/company/notification-defaults')
        ->assertOk()
        ->assertJsonPath('data.0', ['type' => 'invoice_viewed', 'group' => 'sales', 'personal' => false, 'enabled' => true, 'bell' => true, 'mail' => false])
        ->assertJsonMissing(['type' => 'backup_failed']);

    $this->putJson('/api/v1/company/notification-defaults', ['defaults' => ['invoice_viewed' => ['enabled' => false], 'invoice_paid' => ['mail' => true]]])
        ->assertOk()
        ->assertJsonPath('data.0.enabled', false);

    $this->getJson('/api/v1/me/notification-preferences')
        ->assertJsonPath('data.0.enabled', false)
        ->assertJsonFragment(['type' => 'invoice_paid', 'group' => 'sales', 'personal' => false, 'enabled' => true, 'bell' => true, 'mail' => true, 'customised' => ['bell' => false, 'mail' => false]]);
});

test('only the owner may change the defaults, and only for company types', function () {
    $member = defaultsMember($this->companyId, 'preset:manager');
    Sanctum::actingAs($member, ['*']);

    $this->getJson('/api/v1/company/notification-defaults')->assertForbidden();
    $this->putJson('/api/v1/company/notification-defaults', ['defaults' => ['invoice_viewed' => ['enabled' => false]]])->assertForbidden();

    Sanctum::actingAs($this->owner, ['*']);
    $this->putJson('/api/v1/company/notification-defaults', ['defaults' => ['backup_failed' => ['enabled' => false]]])->assertUnprocessable();
    $this->putJson('/api/v1/company/notification-defaults', ['defaults' => ['invoice_viewed' => ['sms' => true]]])->assertUnprocessable();
});

test('platform notices are listed only for super admins', function () {
    $member = defaultsMember($this->companyId);
    Sanctum::actingAs($member, ['*']);

    $this->getJson('/api/v1/me/notification-preferences')->assertOk()->assertJsonMissing(['type' => 'module_disabled']);
});
