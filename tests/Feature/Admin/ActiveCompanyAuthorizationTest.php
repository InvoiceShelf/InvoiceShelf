<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Estimate;
use App\Models\ExchangeRateProvider;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RecurringInvoice;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

/**
 * A role answers for the company it was given in. Someone who runs one company
 * and may only read another must not carry the first company's abilities over
 * to the second company's records by naming the first in the `company` header.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->mine = User::find(1)->companies()->first();
    $this->theirs = Company::factory()->create();
    $this->theirs->setupDefaultData();

    $this->user = User::factory()->create(['role' => 'user']);
    $this->user->companies()->attach([$this->mine->id, $this->theirs->id]);

    BouncerFacade::scope()->to($this->mine->id);
    BouncerFacade::sync($this->user)->roles(['super admin']);

    BouncerFacade::scope()->to($this->theirs->id);
    $viewer = BouncerFacade::role()->create(['name' => 'viewer', 'title' => 'Viewer', 'scope' => $this->theirs->id]);
    foreach (config('abilities.abilities') as $ability) {
        if (str_starts_with($ability['ability'], 'view-') || $ability['ability'] === 'dashboard') {
            BouncerFacade::allow($viewer)->to($ability['ability'], $ability['model']);
        }
    }
    BouncerFacade::sync($this->user)->roles(['viewer']);

    Sanctum::actingAs($this->user, ['*']);
});

/** Point Bouncer at a company, as the request middleware does from the header. */
function actIn(Company $company): void
{
    BouncerFacade::scope()->to($company->id);
    BouncerFacade::refresh();
}

dataset('company records', [
    'invoice' => [Invoice::class],
    'estimate' => [Estimate::class],
    'recurring invoice' => [RecurringInvoice::class],
    'payment' => [Payment::class],
    'payment method' => [PaymentMethod::class],
    'customer' => [Customer::class],
    'item' => [Item::class],
    'unit' => [Unit::class],
    'expense' => [Expense::class],
    'expense category' => [ExpenseCategory::class],
    'tax type' => [TaxType::class],
    'custom field' => [CustomField::class],
    'exchange rate provider' => [ExchangeRateProvider::class],
]);

test('abilities held in one company do not reach another company\'s record', function (string $model) {
    $own = $model::factory()->create(['company_id' => $this->mine->id]);
    $foreign = $model::factory()->create(['company_id' => $this->theirs->id]);

    actIn($this->mine);
    expect(Gate::allows('update', $own))->toBeTrue()
        ->and(Gate::allows('view', $foreign))->toBeFalse()
        ->and(Gate::allows('update', $foreign))->toBeFalse()
        ->and(Gate::allows('delete', $foreign))->toBeFalse();

    actIn($this->theirs);
    expect(Gate::allows('view', $foreign))->toBeTrue()
        ->and(Gate::allows('update', $own))->toBeFalse();
})->with('company records');

test('a view-only role stays view-only on its own company\'s documents', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->theirs->id]);
    $customer = Customer::factory()->create(['company_id' => $this->theirs->id]);

    actIn($this->theirs);
    expect(Gate::allows('update', $invoice))->toBeFalse()
        ->and(Gate::allows('delete', $invoice))->toBeFalse()
        ->and(Gate::allows('update', $customer))->toBeFalse();
});

test('notes, sending, reports and the dashboard follow the same rule', function () {
    $note = Note::factory()->create(['company_id' => $this->theirs->id]);
    $invoice = Invoice::factory()->create(['company_id' => $this->theirs->id]);
    $estimate = Estimate::factory()->create(['company_id' => $this->theirs->id]);
    $payment = Payment::factory()->create(['company_id' => $this->theirs->id]);

    actIn($this->mine);
    expect(Gate::allows('manage notes', $note))->toBeFalse()
        ->and(Gate::allows('view notes', $note))->toBeFalse()
        ->and(Gate::allows('send invoice', $invoice))->toBeFalse()
        ->and(Gate::allows('send estimate', $estimate))->toBeFalse()
        ->and(Gate::allows('send payment', $payment))->toBeFalse()
        ->and(Gate::allows('view report', $this->theirs))->toBeFalse()
        ->and(Gate::allows('view dashboard', $this->theirs))->toBeFalse()
        ->and(Gate::allows('view report', $this->mine))->toBeTrue()
        ->and(Gate::allows('view dashboard', $this->mine))->toBeTrue();
});

test('the invoice endpoints refuse another company\'s invoice whichever company the header names', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->theirs->id,
        'status' => Invoice::STATUS_DRAFT,
        'notes' => 'Original',
    ]);
    $asMine = ['company' => $this->mine->id];

    $this->withHeaders($asMine)->postJson("/api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_SENT])->assertForbidden();
    $this->withHeaders($asMine)->putJson("/api/v1/invoices/{$invoice->id}", Invoice::factory()->raw([
        'company_id' => $this->mine->id,
        'notes' => 'Changed',
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]))->assertForbidden();
    $this->withHeaders($asMine)->postJson("/api/v1/invoices/{$invoice->id}/clone")->assertForbidden();
    $this->withHeaders($asMine)->getJson("/api/v1/invoices/{$invoice->id}")->assertForbidden();

    expect($invoice->fresh())
        ->status->toBe(Invoice::STATUS_DRAFT)
        ->notes->toBe('Original')
        ->company_id->toBe($this->theirs->id);

    // In the company itself the view-only role still reads, and still may not write.
    $asTheirs = ['company' => $this->theirs->id];
    $this->withHeaders($asTheirs)->getJson("/api/v1/invoices/{$invoice->id}")->assertOk();
    $this->withHeaders($asTheirs)->postJson("/api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_SENT])->assertForbidden();
});

test('report and receipt links carry no company header and answer for the record\'s own company', function () {
    $outside = Company::factory()->create();
    $outside->setupDefaultData();

    $open = function (string $path) {
        // The browser opens these directly, so no middleware has told Bouncer which company applies.
        BouncerFacade::scope()->to(null);
        BouncerFacade::refresh();

        return $this->get($path);
    };

    $range = '?from_date=2020-01-01&to_date=2030-01-01';
    foreach (['sales/customers', 'sales/items', 'expenses', 'tax-summary', 'profit-loss'] as $report) {
        $open("/reports/{$report}/{$this->mine->unique_hash}{$range}")->assertOk();
        $open("/reports/{$report}/{$this->theirs->unique_hash}{$range}")->assertOk();
        $open("/reports/{$report}/{$outside->unique_hash}{$range}")->assertForbidden();
    }

    // None of these expenses has a receipt, so past authorization the answer is "no receipt".
    $own = Expense::factory()->create(['company_id' => $this->mine->id]);
    $foreign = Expense::factory()->create(['company_id' => $this->theirs->id]);
    $unreachable = Expense::factory()->create(['company_id' => $outside->id]);

    foreach (['receipt', 'download-receipt'] as $link) {
        expect($open("/reports/expenses/{$own->id}/{$link}")->status())->not->toBe(403)
            ->and($open("/reports/expenses/{$foreign->id}/{$link}")->status())->not->toBe(403);
        $open("/reports/expenses/{$unreachable->id}/{$link}")->assertForbidden();
    }
});
