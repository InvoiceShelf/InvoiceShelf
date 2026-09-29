<?php

use App\Domains\Accounts\Application\InvitationService;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Application\Composition\PaymentComposer;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\Events\McpConnectionBound;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Modules\Events\ModuleIncompatible;
use App\Platform\Notifications\AppNotification;
use App\Platform\Operations\Console\CatchUp;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;
use Spatie\Backup\Events\BackupHasFailed;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::query()->findOrFail(1);
    $this->companyId = (int) $this->owner->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);

    $this->usd = (int) Currency::query()->where('code', 'USD')->value('id');
    CompanySetting::setSettings(['currency' => $this->usd, 'time_zone' => 'UTC'], $this->companyId);
    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd, 'name' => 'Globex']);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A member of the company holding the given role.
 */
function staffMember(int $companyId, string $role = 'owner'): User
{
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($companyId);
    BouncerFacade::scope()->onceTo($companyId, fn () => $member->assign($role));
    BouncerFacade::refresh();

    return $member;
}

function sentInvoice($test, int $total, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'company_id' => $test->companyId,
        'customer_id' => $test->customer->id,
        'currency_id' => $test->usd,
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => $total,
        'due_amount' => $total,
        'base_total' => $total,
        'base_due_amount' => $total,
        'exchange_rate' => 1,
        ...$attributes,
    ]);
}

function noticesOfType(string $type): Collection
{
    return Notification::sent(User::query()->findOrFail(1), AppNotification::class)
        ->filter(fn (AppNotification $notice) => $notice->message->type === $type)
        ->values();
}

test('a customer accepting or rejecting an estimate in the portal tells the company', function () {
    Notification::fake();
    $estimate = Estimate::factory()->create(['company_id' => $this->companyId, 'customer_id' => $this->customer->id, 'status' => Estimate::STATUS_SENT]);
    Sanctum::actingAs($this->customer, ['*'], 'customer');
    $url = "api/v1/{$this->customer->company->slug}/customer/estimate/{$estimate->id}/status";

    $this->postJson($url, ['status' => Estimate::STATUS_ACCEPTED])->assertOk();
    $this->postJson($url, ['status' => Estimate::STATUS_ACCEPTED])->assertOk();
    $this->postJson($url, ['status' => Estimate::STATUS_REJECTED])->assertOk();

    expect(noticesOfType('estimate_accepted'))->toHaveCount(1)
        ->and(noticesOfType('estimate_accepted')->first()->message->params)->toBe(['customer' => 'Globex', 'number' => $estimate->estimate_number])
        ->and(noticesOfType('estimate_rejected'))->toHaveCount(1);
});

test('the portal only takes accepted or rejected as an answer', function () {
    $estimate = Estimate::factory()->create(['company_id' => $this->companyId, 'customer_id' => $this->customer->id, 'status' => Estimate::STATUS_SENT]);
    Sanctum::actingAs($this->customer, ['*'], 'customer');

    $this->postJson("api/v1/{$this->customer->company->slug}/customer/estimate/{$estimate->id}/status", ['status' => 'DRAFT'])
        ->assertUnprocessable();

    expect($estimate->fresh()->status)->toBe(Estimate::STATUS_SENT);
});

test('a payment recorded by a colleague is told to the others, and a paid invoice to everyone', function () {
    Notification::fake();
    $colleague = staffMember($this->companyId);
    $invoice = sentInvoice($this, 10000);
    $payload = app(PaymentComposer::class)->payment(['allocations' => [['invoice_id' => $invoice->id]]], $this->companyId, null);

    $this->postJson('api/v1/payments', $payload)->assertSuccessful();

    Notification::assertSentTo($colleague, AppNotification::class, fn (AppNotification $notice) => $notice->message->type === 'payment_received'
        && $notice->message->variant === 'recorded'
        && $notice->message->params['member'] === $this->owner->name
        && $notice->message->params['customer'] === 'Globex');
    expect(noticesOfType('payment_received'))->toHaveCount(0)
        ->and(noticesOfType('invoice_paid'))->toHaveCount(1)
        ->and(noticesOfType('invoice_paid')->first()->message->params['number'])->toBe($invoice->invoice_number);
    Notification::assertSentTo($colleague, AppNotification::class, fn (AppNotification $notice) => $notice->message->type === 'invoice_paid');
});

test('a part payment does not say the invoice is paid', function () {
    Notification::fake();
    $invoice = sentInvoice($this, 10000);
    $payload = app(PaymentComposer::class)->payment(['amount' => '40', 'allocations' => [['invoice_id' => $invoice->id]]], $this->companyId, null);

    $this->postJson('api/v1/payments', $payload)->assertSuccessful();

    expect(noticesOfType('invoice_paid'))->toHaveCount(0);
});

test('an invoice turning overdue is told once', function () {
    Notification::fake();
    Carbon::setTestNow('2026-06-15 09:00:00');
    $invoice = sentInvoice($this, 5000, ['due_date' => '2026-06-10']);

    $this->artisan('check:invoices:status')->assertSuccessful();
    Invoice::query()->whereKey($invoice->id)->update(['overdue' => false]);
    $this->artisan('check:invoices:status')->assertSuccessful();

    expect(noticesOfType('invoice_overdue'))->toHaveCount(1)
        ->and(noticesOfType('invoice_overdue')->first()->message->url)->toBe("/admin/invoices/{$invoice->id}/view");
});

test('the daily catch-up runs the bill sweep', function () {
    expect(CatchUp::SWEEPS)->toContain('bills:check-due');
});

test('whoever sent an invitation hears the answer', function () {
    Notification::fake();
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    $role = BouncerFacade::scope()->onceTo($this->companyId, fn () => Role::query()->where('name', 'owner')->firstOrFail());
    $invitation = app(InvitationService::class)->invite($this->owner->companies()->first(), 'new@example.com', (int) $role->id, $this->owner);

    app(InvitationService::class)->accept($invitation->fresh(), $invitee);

    expect(noticesOfType('invitation_accepted'))->toHaveCount(1)
        ->and(noticesOfType('invitation_accepted')->first()->message->params['email'])->toBe('new@example.com');
    Notification::assertNotSentTo($invitee, AppNotification::class, fn ($n) => str_starts_with($n->message->type, 'invitation'));
});

test('a new AI connection is told to its user and to the company owner', function () {
    Notification::fake();
    $member = staffMember($this->companyId, 'preset:read-only');
    $connection = McpConnection::query()->forceCreate([
        'user_id' => $member->id,
        'oauth_client_id' => (string) Str::uuid(),
        'company_id' => $this->companyId,
        'access' => 'read',
        'client_name' => 'Claude',
    ]);

    McpConnectionBound::dispatch((int) $connection->id, (int) $member->id, $this->companyId);

    Notification::assertSentTo($member, AppNotification::class, fn ($n) => $n->message->type === 'ai_connection_added' && $n->message->variant === null);
    expect(noticesOfType('ai_connection_added'))->toHaveCount(1)
        ->and(noticesOfType('ai_connection_added')->first()->message->variant)->toBe('member');
});

test('module and backup problems go to super admins as platform notices', function () {
    Notification::fake();
    $admin = User::query()->where('role', 'super admin')->firstOrFail();
    $member = staffMember($this->companyId);

    ModuleIncompatible::dispatch('Invoicing', 'Needs InvoiceShelf 4.');
    event(new BackupHasFailed(new Exception('Disk full'), 'local'));

    Notification::assertSentTo($admin, AppNotification::class, fn ($n) => $n->message->type === 'module_disabled' && $n->message->companyId === null);
    Notification::assertSentTo($admin, AppNotification::class, fn ($n) => $n->message->type === 'backup_failed' && $n->message->params['error'] === 'Disk full');
    Notification::assertNotSentTo($member, AppNotification::class);
});
