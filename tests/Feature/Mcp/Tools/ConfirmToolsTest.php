<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Mail\SendEstimateMail;
use App\Domains\Sales\Mail\SendInvoiceMail;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\Models\McpActivity;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\Purchases\DeleteExpenseTool;
use App\Platform\Mcp\Tools\Receivables\DeletePaymentTool;
use App\Platform\Mcp\Tools\Receivables\RecordPaymentTool;
use App\Platform\Mcp\Tools\Receivables\SendPaymentReceiptTool;
use App\Platform\Mcp\Tools\Sales\CreateEstimateTool;
use App\Platform\Mcp\Tools\Sales\CreateInvoiceTool;
use App\Platform\Mcp\Tools\Sales\DeleteEstimateTool;
use App\Platform\Mcp\Tools\Sales\DeleteInvoiceTool;
use App\Platform\Mcp\Tools\Sales\SendEstimateTool;
use App\Platform\Mcp\Tools\Sales\SendInvoiceTool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\McpTesting;

beforeEach(function () {
    Queue::fake();
    Mail::fake();

    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $usd = Currency::where('code', 'USD')->value('id');

    CompanySetting::setSettings([
        'currency' => $usd,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'invoice_mail_body' => 'Please find invoice {INVOICE_NUMBER} attached.',
        'estimate_mail_body' => 'Please find estimate {ESTIMATE_NUMBER} attached.',
        'payment_mail_body' => 'Thank you.',
    ], $this->companyId);
    config(['mail.from.address' => 'billing@acme.test']);

    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'email' => 'ap@customer.test', 'currency_id' => $usd]);
    $this->context = McpTesting::actAs($this->user, $this->companyId, McpConnection::ACCESS_WRITE);

    $this->invoice = McpTesting::call($this->user, CreateInvoiceTool::class, [
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'Consulting', 'quantity' => 2, 'unit_price' => '100']],
    ]);
});

test('an invoice is not sent without confirm', function () {
    $answer = McpTesting::call($this->user, SendInvoiceTool::class, ['invoice_id' => $this->invoice['id']]);

    expect($answer['aborted'])->toBeTrue()
        ->and($answer['message'])->toContain('confirm set to true')
        ->and(Invoice::find($this->invoice['id'])->status)->toBe(Invoice::STATUS_DRAFT);

    Mail::assertNothingSent();
});

test('a confirmed invoice is emailed the way the send dialog would', function () {
    $answer = McpTesting::call($this->user, SendInvoiceTool::class, ['invoice_id' => $this->invoice['id'], 'confirm' => true]);

    expect($answer)->toMatchArray(['sent_to' => 'ap@customer.test', 'status' => Invoice::STATUS_SENT]);

    Mail::assertSent(SendInvoiceMail::class, fn (SendInvoiceMail $mail) => $mail->hasTo('ap@customer.test'));
});

test('the recipient and message can be given', function () {
    McpTesting::call($this->user, SendInvoiceTool::class, [
        'invoice_id' => $this->invoice['id'],
        'to' => 'accounts@customer.test',
        'cc' => 'boss@customer.test',
        'subject' => 'Your invoice',
        'confirm' => true,
    ]);

    Mail::assertSent(SendInvoiceMail::class, fn (SendInvoiceMail $mail) => $mail->hasTo('accounts@customer.test') && $mail->hasCc('boss@customer.test'));
});

test('estimates and payment receipts are sent too', function () {
    $estimate = McpTesting::call($this->user, CreateEstimateTool::class, [
        'customer_id' => $this->customer->id,
        'lines' => [['name' => 'Build', 'unit_price' => '500']],
    ]);
    Invoice::find($this->invoice['id'])->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);
    $payment = McpTesting::call($this->user, RecordPaymentTool::class, ['allocations' => [['invoice_id' => $this->invoice['id']]]]);

    expect(McpTesting::call($this->user, SendEstimateTool::class, ['estimate_id' => $estimate['id'], 'confirm' => true])['status'])->toBe(Estimate::STATUS_SENT)
        ->and(McpTesting::call($this->user, SendPaymentReceiptTool::class, ['payment_id' => $payment['id'], 'confirm' => true])['sent_to'])->toBe('ap@customer.test');

    Mail::assertSent(SendEstimateMail::class);
});

test('a connection may send a limited number of emails an hour', function () {
    for ($i = 0; $i < SendInvoiceTool::SENDS_PER_HOUR; $i++) {
        RateLimiter::hit('mcp-sends:connection:'.$this->context->connection->id, 3600);
    }

    InvoiceShelfServer::actingAs($this->user)
        ->tool(SendInvoiceTool::class, ['invoice_id' => $this->invoice['id'], 'confirm' => true])
        ->assertHasErrors();

    Mail::assertNothingSent();
});

test('a company may be sent a limited number of emails a day across its connections', function () {
    for ($i = 0; $i < SendInvoiceTool::SENDS_PER_DAY; $i++) {
        RateLimiter::hit('mcp-sends:company:'.$this->companyId, 86400);
    }

    InvoiceShelfServer::actingAs($this->user)
        ->tool(SendInvoiceTool::class, ['invoice_id' => $this->invoice['id'], 'confirm' => true])
        ->assertHasErrors();

    Mail::assertNothingSent();
});

test('an invoice is deleted only when confirmed and unpaid', function () {
    expect(McpTesting::call($this->user, DeleteInvoiceTool::class, ['invoice_id' => $this->invoice['id']])['aborted'])->toBeTrue()
        ->and(Invoice::find($this->invoice['id']))->not->toBeNull();

    Invoice::find($this->invoice['id'])->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);
    McpTesting::call($this->user, RecordPaymentTool::class, ['amount' => '50', 'allocations' => [['invoice_id' => $this->invoice['id']]]]);

    InvoiceShelfServer::actingAs($this->user)
        ->tool(DeleteInvoiceTool::class, ['invoice_id' => $this->invoice['id'], 'confirm' => true])
        ->assertHasErrors(["Invoice {$this->invoice['number']} has payments against it. Delete or move those payments first."]);

    McpTesting::call($this->user, DeletePaymentTool::class, ['payment_number' => Payment::query()->value('payment_number'), 'confirm' => true]);

    expect(Invoice::find($this->invoice['id'])->due_amount)->toBe(20000);

    McpTesting::call($this->user, DeleteInvoiceTool::class, ['invoice_id' => $this->invoice['id'], 'confirm' => true]);

    expect(Invoice::find($this->invoice['id']))->toBeNull();
});

test('estimates and expenses are deleted when confirmed', function () {
    $estimate = McpTesting::call($this->user, CreateEstimateTool::class, ['customer_id' => $this->customer->id, 'lines' => [['name' => 'Build', 'unit_price' => '500']]]);
    $expense = Expense::factory()->create(['company_id' => $this->companyId]);

    McpTesting::call($this->user, DeleteEstimateTool::class, ['estimate_id' => $estimate['id'], 'confirm' => true]);
    McpTesting::call($this->user, DeleteExpenseTool::class, ['expense_id' => $expense->id]);

    expect(Estimate::find($estimate['id']))->toBeNull()
        ->and(Expense::find($expense->id))->not->toBeNull();

    McpTesting::call($this->user, DeleteExpenseTool::class, ['expense_id' => $expense->id, 'confirm' => true]);

    expect(Expense::find($expense->id))->toBeNull();
});

test('every change and email is logged, and an aborted call is not', function () {
    McpTesting::call($this->user, SendInvoiceTool::class, ['invoice_id' => $this->invoice['id']]);
    McpTesting::call($this->user, SendInvoiceTool::class, ['invoice_id' => $this->invoice['id'], 'confirm' => true]);

    expect(McpActivity::query()->orderBy('id')->get(['tool', 'subject_id', 'user_id', 'company_id', 'mcp_connection_id'])->toArray())->toBe([
        ['tool' => 'create_invoice', 'subject_id' => $this->invoice['id'], 'user_id' => $this->user->id, 'company_id' => $this->companyId, 'mcp_connection_id' => $this->context->connection->id],
        ['tool' => 'send_invoice', 'subject_id' => $this->invoice['id'], 'user_id' => $this->user->id, 'company_id' => $this->companyId, 'mcp_connection_id' => $this->context->connection->id],
    ]);
});

test('the log is pruned after a year', function () {
    McpActivity::query()->create(['mcp_connection_id' => 1, 'user_id' => 1, 'company_id' => $this->companyId, 'tool' => 'create_invoice', 'created_at' => now()->subDays(400)]);

    Artisan::call('mcp:prune');

    expect(McpActivity::query()->pluck('tool')->all())->toBe(['create_invoice'])
        ->and(McpActivity::query()->where('created_at', '<', now()->subYear())->exists())->toBeFalse();
});
