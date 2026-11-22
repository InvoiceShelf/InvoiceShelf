<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\Note;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
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

    $this->mine = User::query()->where('role', 'super admin')->firstOrFail()->companies()->firstOrFail();
    $this->theirs = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    app(CompanyService::class)->setupDefaults($this->theirs);

    $this->user = User::factory()->create(['role' => 'user']);
    $this->user->companies()->attach([$this->mine->id, $this->theirs->id]);
    BouncerFacade::scope()->onceTo($this->mine->id, fn () => $this->user->assign('owner'));
    BouncerFacade::scope()->onceTo($this->theirs->id, fn () => $this->user->assign('preset:read-only'));

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
        ->and(Gate::allows('update', $foreign))->toBeFalse()
        ->and(Gate::allows('update', $own))->toBeFalse();
})->with('company records');

test('notes, credit notes, reports and the dashboard follow the same rule', function () {
    $note = Note::factory()->create(['company_id' => $this->theirs->id]);
    $invoice = Invoice::factory()->create(['company_id' => $this->theirs->id]);

    actIn($this->mine);
    expect(Gate::allows('manage notes', $note))->toBeFalse()
        ->and(Gate::allows('view notes', $note))->toBeFalse()
        ->and(Gate::allows('send', $invoice))->toBeFalse()
        ->and(Gate::allows('create credit note', $invoice))->toBeFalse()
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

    // In the company itself the read-only role still reads, and still may not write.
    $asTheirs = ['company' => $this->theirs->id];
    $this->withHeaders($asTheirs)->getJson("/api/v1/invoices/{$invoice->id}")->assertOk();
    $this->withHeaders($asTheirs)->postJson("/api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_SENT])->assertForbidden();
});

test('receipt links carry no company header and answer for the expense\'s own company', function () {
    $own = Expense::factory()->create(['company_id' => $this->mine->id]);
    $foreign = Expense::factory()->create(['company_id' => $this->theirs->id]);
    $outside = Expense::factory()->create(['company_id' => Company::factory()->create(['owner_id' => $this->theirs->owner_id])->id]);

    $open = function (Expense $expense, string $link) {
        // The browser opens these directly, so no middleware has told Bouncer which company applies.
        BouncerFacade::scope()->to(null);
        BouncerFacade::refresh();

        return $this->get("/reports/expenses/{$expense->id}/{$link}");
    };

    // None of these expenses has a receipt, so past authorization the answer is "no receipt".
    foreach (['receipt', 'download-receipt'] as $link) {
        expect($open($own, $link)->status())->not->toBe(403)
            ->and($open($foreign, $link)->status())->not->toBe(403);
        $open($outside, $link)->assertForbidden();
    }
});
