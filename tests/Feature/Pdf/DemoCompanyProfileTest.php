<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * The demo company had no address row at all, and Invoice::getCompanyAddress()
 * returns false outright in that case — so every seeded document rendered with
 * an empty company block, and the name only appeared via the header's no-logo
 * fallback. These guard the data the PDF actually needs.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::where('email', 'demo@invoiceshelf.com')->firstOrFail();
    $this->company = $this->user->companies()->firstOrFail();

    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->user, ['*']);
});

test('the demo company is Acme Inc', function () {
    expect($this->company->name)->toBe('Acme Inc')
        ->and($this->company->slug)->toBe('acme-inc');
});

test('the demo company has an address the documents can render', function () {
    $address = $this->company->address;

    expect($address)->not->toBeNull()
        ->and($address->address_street_1)->not->toBeEmpty()
        ->and($address->city)->not->toBeEmpty()
        ->and($address->state)->not->toBeEmpty()
        ->and($address->zip)->not->toBeEmpty()
        ->and($address->phone)->not->toBeEmpty()
        // {COMPANY_COUNTRY} resolves through the relation, so an id is required
        // rather than a country name string.
        ->and($address->country_name)->not->toBeEmpty();
});

/**
 * Company::address() is an unscoped hasOne, so any address carrying this
 * company_id is treated as the company's own. Customer addresses must leave it
 * null or the company picks one of them up and CompanyResource walks a circular
 * reference.
 */
test('the company address is the only address bound to the company', function () {
    expect(Company::find($this->company->id)->address()->count())->toBe(1);
});

/**
 * Migration 2025_08_18 inserts Algerian Dinar via firstOrCreate() before any
 * seeder runs, so currency id 1 is DZD on a fresh install and the old
 * hardcoded `'currency' => 1` priced the demo in "DA".
 */
test('the demo company is priced in USD, not whatever landed at id 1', function () {
    $currencyId = CompanySetting::getSetting('currency', $this->company->id);

    expect(Currency::find($currencyId)->code)->toBe('USD');
});

test('the company block reaches the rendered document', function () {
    config(['pdf.driver' => 'dompdf']);

    $invoice = Invoice::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => 'invoice1',
    ]);

    get("/invoices/pdf/{$invoice->unique_hash}?preview=true")
        ->assertOk()
        ->assertSee('Acme Inc')
        ->assertSee($this->company->address->address_street_1)
        ->assertSee($this->company->address->phone);
});
