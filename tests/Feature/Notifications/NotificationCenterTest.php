<?php

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Events\EstimateViewed;
use App\Domains\Sales\Events\InvoiceViewed;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Contracts\EmailLogWriter;
use App\Platform\Notifications\Application\NotificationCenter;
use App\Platform\Notifications\Application\NotificationPreferences;
use App\Platform\Notifications\AppNotification;
use App\Platform\Notifications\NotificationMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

/**
 * A member of the company holding the given role, or none.
 */
function companyMember(int $companyId, ?string $role = null): User
{
    $member = User::factory()->create();
    $member->companies()->attach($companyId);

    if ($role !== null) {
        BouncerFacade::scope()->onceTo($companyId, fn () => $member->assign($role));
        BouncerFacade::refresh();
    }

    return $member;
}

function viewedMessage(Invoice $invoice): NotificationMessage
{
    return new NotificationMessage(
        type: 'invoice_viewed',
        companyId: (int) $invoice->company_id,
        subject: $invoice,
        params: ['customer' => 'Acme', 'number' => (string) $invoice->invoice_number],
        url: "/admin/invoices/{$invoice->id}/view",
    );
}

test('a company notice reaches the members who may see that kind of record, and nobody else', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    $reader = companyMember($this->companyId, 'preset:read-only');
    $bystander = companyMember($this->companyId);
    $otherCompany = Company::factory()->create();
    $outsider = companyMember((int) $otherCompany->id, 'owner');

    $sent = app(NotificationCenter::class)->send(viewedMessage($invoice));

    expect($sent)->toBe(2);
    Notification::assertSentTo([$this->owner, $reader], AppNotification::class);
    Notification::assertNotSentTo([$bystander, $outsider], AppNotification::class);
});

test('whoever did it can be left out', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);

    app(NotificationCenter::class)->send(viewedMessage($invoice), except: [(int) $this->owner->id]);

    Notification::assertNotSentTo($this->owner, AppNotification::class);
});

test('a personal notice reaches its person only while they are a member', function () {
    Notification::fake();
    $leaver = companyMember($this->companyId, 'owner');
    $message = new NotificationMessage('recurring_invoice_failed', $this->companyId, params: ['customer' => 'Acme', 'reason' => 'errors.recurring_invoice_failed'], translate: ['reason']);

    expect(app(NotificationCenter::class)->send($message, $leaver))->toBe(1);

    $leaver->companies()->detach($this->companyId);
    expect(app(NotificationCenter::class)->send($message, $leaver))->toBe(0)
        ->and(app(NotificationCenter::class)->send($message))->toBe(0);
});

test('the bell stores the company, the record and the words to show', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);

    app(NotificationCenter::class)->send(viewedMessage($invoice));

    $notice = $this->owner->notifications()->sole();
    expect($notice->type)->toBe('invoice_viewed')
        ->and((int) $notice->company_id)->toBe($this->companyId)
        ->and($notice->subject_type)->toBe('invoice')
        ->and((int) $notice->subject_id)->toBe($invoice->id)
        ->and($notice->data)->toMatchArray([
            'title' => 'inbox.types.invoice_viewed.title',
            'body' => 'inbox.types.invoice_viewed.body',
            'params' => ['customer' => 'Acme', 'number' => $invoice->invoice_number],
            'url' => "/admin/invoices/{$invoice->id}/view",
        ]);
});

test('each person chooses the bell, email, both or neither', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    $preferences = app(NotificationPreferences::class);
    $center = app(NotificationCenter::class);
    $channels = function () {
        $sent = Notification::sent($this->owner, AppNotification::class)->last();

        return $sent?->channels;
    };

    // Customer activity is in the bell only, until email is switched on.
    $center->send(viewedMessage($invoice));
    expect($channels())->toBe(['database']);

    $preferences->update($this->owner, ['invoice_viewed' => ['mail' => true]]);
    $center->send(viewedMessage($invoice));
    expect($channels())->toBe(['database', 'mail']);

    $preferences->update($this->owner, ['invoice_viewed' => ['bell' => false]]);
    $center->send(viewedMessage($invoice));
    expect($channels())->toBe(['mail']);

    $preferences->update($this->owner, ['invoice_viewed' => ['mail' => false]]);
    expect($center->send(viewedMessage($invoice)))->toBe(0);
});

test('the email is written in the recipient\'s language', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    CompanySetting::setSettings(['language' => 'de'], $this->companyId);
    $this->owner->setSettings(['language' => 'default']);
    app(NotificationPreferences::class)->update($this->owner, ['invoice_viewed' => ['mail' => true]]);
    Notification::fake();

    app(NotificationCenter::class)->send(viewedMessage($invoice));

    Notification::assertSentTo($this->owner, AppNotification::class, function (AppNotification $notice) use ($invoice) {
        $mail = $notice->toMail($this->owner);

        return $notice->mailLocale === 'de'
            && $mail->subject !== ''
            && str_contains($mail->viewData['body'], (string) $invoice->invoice_number)
            && $mail->viewData['url'] === url("/admin/invoices/{$invoice->id}/view");
    });
});

test('a failed email does not stop the others, nor the action it is about', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    $reader = companyMember($this->companyId, 'preset:read-only');
    app(NotificationPreferences::class)->update($this->owner, ['invoice_viewed' => ['mail' => true]]);
    Event::listen(NotificationSending::class, function ($event) {
        if ($event->channel === 'mail') {
            throw new RuntimeException('SMTP is down');
        }
    });

    app(NotificationCenter::class)->send(viewedMessage($invoice));

    expect($reader->notifications()->count())->toBe(1);
});

test('opening an emailed invoice tells its members, once', function () {
    Event::fake([InvoiceViewed::class]);
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId, 'status' => Invoice::STATUS_SENT]);
    $token = app(EmailLogWriter::class)->record($invoice, ['from' => 'a@example.com', 'to' => 'b@example.com', 'subject' => 'x', 'body' => 'y']);

    $this->withoutVite()->get('/customer/invoices/view/'.$token)->assertOk();
    $this->withoutVite()->get('/customer/invoices/view/'.$token)->assertOk();

    Event::assertDispatchedTimes(InvoiceViewed::class, 1);
    Event::assertDispatched(InvoiceViewed::class, fn ($event) => $event->invoiceId === $invoice->id && $event->companyId === $this->companyId);
});

test('opening an emailed estimate tells its members', function () {
    Event::fake([EstimateViewed::class]);
    $estimate = Estimate::factory()->create(['company_id' => $this->companyId, 'status' => Estimate::STATUS_SENT]);
    $token = app(EmailLogWriter::class)->record($estimate, ['from' => 'a@example.com', 'to' => 'b@example.com', 'subject' => 'x', 'body' => 'y']);

    $this->withoutVite()->get('/customer/estimates/view/'.$token)->assertOk();

    Event::assertDispatched(EstimateViewed::class, fn ($event) => $event->estimateId === $estimate->id);
});

test('a viewed document goes to the bell, and to the company mailbox when it asked', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    CompanySetting::setSettings(['notify_invoice_viewed' => 'NO', 'notification_email' => 'office@example.com'], $this->companyId);

    InvoiceViewed::dispatch($invoice->id, $this->companyId);

    Notification::assertSentTo($this->owner, AppNotification::class, fn (AppNotification $notice) => $notice->message->type === 'invoice_viewed');
    Notification::assertNotSentTo(new AnonymousNotifiable, AppNotification::class);

    CompanySetting::setSettings(['notify_invoice_viewed' => 'YES'], $this->companyId);
    InvoiceViewed::dispatch($invoice->id, $this->companyId);

    Notification::assertSentOnDemand(AppNotification::class, fn (AppNotification $notice, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'office@example.com'
        && $channels === ['mail']);
});

test('old notices are pruned, unread ones kept longer', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    Carbon::setTestNow('2026-01-01 12:00:00');
    app(NotificationCenter::class)->send(viewedMessage($invoice));
    app(NotificationCenter::class)->send(viewedMessage($invoice));
    $this->owner->notifications()->first()->markAsRead();

    Carbon::setTestNow('2026-05-01 12:00:00');
    $this->artisan('notifications:prune')->assertSuccessful();
    expect($this->owner->notifications()->count())->toBe(1)
        ->and($this->owner->notifications()->sole()->read_at)->toBeNull();

    Carbon::setTestNow('2026-07-15 12:00:00');
    $this->artisan('notifications:prune')->assertSuccessful();
    expect($this->owner->notifications()->count())->toBe(0);

    Carbon::setTestNow();
});

test('leaving a company clears its notices, and a deleted account clears them all', function () {
    $member = companyMember($this->companyId, 'owner');
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);
    app(NotificationCenter::class)->send(viewedMessage($invoice));
    DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'platform',
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'data' => [],
    ]);

    app(AccessRevoker::class)->revokeCompany($member->id, $this->companyId);
    expect($member->notifications()->count())->toBe(1)
        ->and($this->owner->notifications()->count())->toBe(1);

    app(AccessRevoker::class)->revokeUser($member->id);
    expect($member->notifications()->count())->toBe(0);
});
