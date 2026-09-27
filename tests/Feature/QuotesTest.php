<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Mail\QuoteViewedMail;
use App\Domains\Sales\Mail\SendQuoteMail;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\Quote;
use App\Domains\Sales\Models\QuoteItem;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Mail\Models\EmailLog;
use App\Support\PublicToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    $this->user = User::findOrFail(1);
    $this->company = $this->user->companies()->firstOrFail();
    $this->withHeader('company', $this->company->id);
    Sanctum::actingAs($this->user, ['*']);
    Queue::fake();
    Mail::fake();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'currency_id' => CompanySetting::getSetting('currency', $this->company->id)]);
});

function quotePayload(Customer $customer, string $number = 'QUO-000001'): array
{
    return [
        'quote_number' => $number, 'quote_date' => '2026-09-27', 'expiry_date' => '2026-10-27',
        'customer_id' => $customer->id, 'currency_id' => $customer->currency_id,
        'template_name' => 'quote1', 'discount' => 0, 'discount_type' => 'fixed', 'discount_val' => 0,
        'sub_total' => 1, 'tax' => 0, 'total' => 1, 'exchange_rate' => 1, 'notes' => 'Website design proposal.',
        'items' => [['name' => 'Design', 'quantity' => 2, 'price' => 12500, 'discount' => 0, 'discount_type' => 'fixed', 'discount_val' => 0, 'tax' => 0, 'total' => 1]],
        'taxes' => [],
    ];
}

test('quotes persist separately and recalculate submitted totals', function () {
    $estimates = Estimate::count();
    $response = $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated();
    $id = $response->json('data.id');
    $this->assertDatabaseHas('quotes', ['id' => $id, 'quote_number' => 'QUO-000001', 'total' => 25000, 'company_id' => $this->company->id]);
    $this->assertDatabaseHas('quote_items', ['quote_id' => $id, 'total' => 25000]);
    expect(Estimate::count())->toBe($estimates);
    $this->getJson('/api/v1/quotes')->assertOk()->assertJsonPath('data.0.quote_number', 'QUO-000001');
    $this->getJson('/api/v1/estimates')->assertOk()->assertJsonCount($estimates, 'data');
});

test('quote updates and duplicates keep separate numbering and storage', function () {
    $id = $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated()->json('data.id');
    $payload = quotePayload($this->customer);
    $payload['items'][0]['quantity'] = 3;
    $this->putJson('/api/v1/quotes/'.$id, $payload)->assertOk()->assertJsonPath('data.total', 37500);
    $clone = $this->postJson('/api/v1/quotes/'.$id.'/clone')->assertSuccessful()->json('data');
    expect($clone['quote_number'])->not->toBe('QUO-000001');
    $this->assertDatabaseHas('quote_items', ['quote_id' => $clone['id'], 'quantity' => 3]);
    $this->postJson('/api/v1/quotes/delete', ['ids' => [$id]])->assertOk();
    $this->assertDatabaseMissing('quote_items', ['quote_id' => $id]);
});

test('quotes convert through the shared invoice workflow and keep the source', function () {
    $id = $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated()->json('data.id');
    $invoice = $this->postJson('/api/v1/quotes/'.$id.'/convert-to-invoice')->assertSuccessful()->json('data');
    $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'total' => 25000, 'status' => Invoice::STATUS_DRAFT]);
    $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice['id'], 'price' => 12500]);
    expect(Quote::find($id))->not->toBeNull();
});

test('quote numbers and templates are independently validated', function () {
    $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated();
    $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertUnprocessable()->assertJsonValidationErrors('quote_number');
    $this->getJson('/api/v1/next-number?key=quote')->assertOk()->assertJsonPath('nextNumber', 'QUO-000002');
    $this->getJson('/api/v1/next-number?key=estimate')->assertOk()->assertJsonPath('nextNumber', 'EST-000001');
    $this->getJson('/api/v1/quotes/templates')->assertOk()->assertJsonPath('quoteTemplates.0.name', 'quote1');
});

test('quote endpoints reject other companies and do not leak through selection filters', function () {
    $id = $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated()->json('data.id');
    $other = Company::factory()->create();
    $foreign = Quote::findOrFail($id)->replicate();
    $foreign->company_id = $other->id;
    $foreign->unique_hash = PublicToken::make();
    $foreign->save();
    $this->getJson('/api/v1/quotes/'.$foreign->id)->assertForbidden();
    $this->getJson('/api/v1/quotes?quote_id='.$foreign->id)->assertOk()->assertJsonCount(0, 'data');
    $this->putJson('/api/v1/quotes/'.$foreign->id, quotePayload($this->customer))->assertForbidden();
    $foreignCustomer = Customer::factory()->create(['company_id' => $other->id]);
    $this->postJson('/api/v1/quotes', quotePayload($foreignCustomer, 'QUO-000009'))->assertUnprocessable()->assertJsonValidationErrors('customer_id');
});

test('estimate permissions do not grant access to quotes', function () {
    $user = User::factory()->create();
    $user->companies()->attach($this->company->id);
    BouncerFacade::scope()->onceTo($this->company->id, function () use ($user) {
        BouncerFacade::allow($user)->to('view-estimate', Estimate::class);
    });
    BouncerFacade::refresh();
    Sanctum::actingAs($user, ['*']);
    $this->getJson('/api/v1/estimates')->assertOk();
    $this->getJson('/api/v1/quotes')->assertForbidden();
    $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertForbidden();
});

test('quote pdf and email preserve their identity and public tokens cannot open estimates', function () {
    $id = $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated()->json('data.id');
    $quote = Quote::findOrFail($id);
    $this->get('/quotes/pdf/'.$quote->unique_hash.'?preview=1')->assertOk()->assertSee('Quote')->assertDontSee('pdf_quote_label')->assertSee('QUO-000001');
    $pdf = $this->get('/quotes/pdf/'.$quote->unique_hash)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
    $envelope = ['subject' => 'Your quote', 'body' => 'Quote {QUOTE_NUMBER}', 'from' => 'sender@example.test', 'to' => 'customer@example.test'];
    $this->get('/api/v1/quotes/'.$id.'/send/preview?'.http_build_query($envelope))->assertOk()->assertSee('QUO-000001');
    $this->postJson('/api/v1/quotes/'.$id.'/send', $envelope)->assertOk();
    Mail::assertSent(SendQuoteMail::class, function ($mail) use ($id) {
        $html = $mail->render();
        expect($html)->toContain('QUO-000001');
        $log = EmailLog::where('mailable_type', 'quote')->where('mailable_id', $id)->firstOrFail();
        $this->getJson('/customer/quotes/'.$log->token)->assertOk()->assertJsonPath('data.quote_number', 'QUO-000001');
        $this->getJson('/customer/estimates/'.$log->token)->assertNotFound();

        return true;
    });
});

test('quote taxes and fields survive clone and conversion without cross-linking document owners', function () {
    $tax = TaxType::factory()->create(['percent' => 10]);
    $field = CustomField::factory()->create(['model_type' => 'Quote', 'type' => 'Text', 'is_required' => false]);
    $payload = quotePayload($this->customer);
    $payload['taxes'] = [['tax_type_id' => $tax->id, 'name' => $tax->name, 'percent' => 10, 'amount' => 2500, 'compound_tax' => false]];
    $payload['customFields'] = [['id' => $field->id, 'value' => 'Design brief']];
    $id = $this->postJson('/api/v1/quotes', $payload)->assertCreated()->json('data.id');
    $this->assertDatabaseHas('taxes', ['quote_id' => $id, 'amount' => 2500, 'estimate_id' => null, 'invoice_id' => null]);
    $clone = $this->postJson('/api/v1/quotes/'.$id.'/clone')->assertSuccessful()->json('data.id');
    expect(Quote::find($clone)->fields()->first()->defaultAnswer)->toBe('Design brief');
    $invoice = $this->postJson('/api/v1/quotes/'.$id.'/convert-to-invoice')->assertSuccessful()->json('data.id');
    $this->assertDatabaseHas('taxes', ['invoice_id' => $invoice, 'quote_id' => null, 'amount' => 2500]);
    $this->postJson('/api/v1/quotes/delete', ['ids' => [$id]])->assertOk();
    $this->assertDatabaseMissing('taxes', ['quote_id' => $id]);
    expect(Invoice::find($invoice)->taxes()->count())->toBe(1);
});

test('customers see only their sent quotes and may only accept or reject a current quote', function () {
    $id = $this->postJson('/api/v1/quotes', quotePayload($this->customer))->assertCreated()->json('data.id');
    $prefix = '/api/v1/'.$this->company->slug.'/customer';
    Sanctum::actingAs($this->customer, ['*'], 'customer');
    $this->getJson($prefix.'/quotes')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson($prefix.'/quotes/'.$id)->assertNotFound();
    Quote::find($id)->update(['status' => 'SENT', 'expiry_date' => now()->addWeek()->toDateString()]);
    $this->getJson($prefix.'/quotes')->assertOk()->assertJsonCount(1, 'data');
    $this->postJson($prefix.'/quote/'.$id.'/status', ['status' => 'PAID'])->assertUnprocessable();
    $this->postJson($prefix.'/quote/'.$id.'/status', ['status' => 'ACCEPTED'])->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
    Quote::find($id)->update(['status' => 'SENT', 'expiry_date' => now()->subDay()->toDateString()]);
    $this->postJson($prefix.'/quote/'.$id.'/status', ['status' => 'ACCEPTED'])->assertUnprocessable();
    Artisan::call('check:quotes:status');
    expect(Quote::find($id)->status)->toBe('EXPIRED');
    $otherCustomer = Customer::factory()->create(['company_id' => $this->company->id]);
    Sanctum::actingAs($otherCustomer, ['*'], 'customer');
    $this->getJson($prefix.'/quotes/'.$id)->assertNotFound();
});

test('quotes use customer currency and retain exchange rates when converted', function () {
    $currency = Currency::where('code', 'EUR')->firstOrFail();
    $this->customer->update(['currency_id' => $currency->id]);
    $payload = quotePayload($this->customer);
    $payload['currency_id'] = CompanySetting::getSetting('currency', $this->company->id);
    $payload['exchange_rate'] = 2;
    $id = $this->postJson('/api/v1/quotes', $payload)->assertCreated()->json('data.id');
    $this->assertDatabaseHas('quotes', ['id' => $id, 'currency_id' => $currency->id, 'total' => 25000, 'base_total' => 50000]);
    $invoice = $this->postJson('/api/v1/quotes/'.$id.'/convert-to-invoice')->assertSuccessful()->json('data.id');
    $this->assertDatabaseHas('invoices', ['id' => $invoice, 'currency_id' => $currency->id, 'total' => 25000, 'base_total' => 50000]);
    $payload['quote_number'] = 'QUO-000002';
    $payload['exchange_rate'] = 0;
    $this->postJson('/api/v1/quotes', $payload)->assertUnprocessable()->assertJsonValidationErrors('exchange_rate');
});

test('a failed line write rolls back the entire quote', function () {
    $event = 'eloquent.creating: '.QuoteItem::class;
    Event::listen($event, fn () => throw new RuntimeException('Simulated line failure'));
    $this->withoutExceptionHandling();
    try {
        $this->postJson('/api/v1/quotes', quotePayload($this->customer));
        $this->fail('Expected the line write to fail');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Simulated line failure');
        $this->assertDatabaseMissing('quotes', ['quote_number' => 'QUO-000001']);
        $this->assertDatabaseCount('quote_items', 0);
    } finally {
        Event::forget($event);
    }
});

test('custom quote designs use their own templates and shared document rendering', function () {
    Storage::fake('pdf_templates');
    $disk = Storage::disk('pdf_templates');
    app('view')->addNamespace('pdf_templates', $disk->path(''));
    $this->artisan('make:template', ['name' => 'branded', '--type' => 'quote'])->assertSuccessful();
    $disk->assertExists('quote/partials/branded/table.blade.php');
    expect($disk->get('quote/branded.blade.php'))->toContain('pdf_templates::quote.partials.branded.table');
    $payload = quotePayload($this->customer);
    $payload['template_name'] = 'branded';
    $id = $this->postJson('/api/v1/quotes', $payload)->assertCreated()->json('data.id');
    $quote = Quote::find($id);
    $this->get('/quotes/pdf/'.$quote->unique_hash.'?preview=1')->assertOk()->assertSee('Quote')->assertSee('QUO-000001');
});

test('quote line taxes and custom fields stay with their copied owners', function () {
    CompanySetting::setSettings(['tax_per_item' => 'YES'], $this->company->id);
    $tax = TaxType::factory()->create(['percent' => 10]);
    $field = CustomField::factory()->create(['model_type' => 'Item', 'type' => 'Text', 'is_required' => false]);
    $payload = quotePayload($this->customer);
    $payload['items'][0]['tax'] = 2500;
    $payload['items'][0]['taxes'] = [['tax_type_id' => $tax->id, 'name' => $tax->name, 'amount' => 2500, 'percent' => 10]];
    $payload['items'][0]['custom_fields'] = [['id' => $field->id, 'value' => 'Milestone one']];
    $id = $this->postJson('/api/v1/quotes', $payload)->assertCreated()->json('data.id');
    $line = Quote::find($id)->items()->firstOrFail();
    $this->assertDatabaseHas('taxes', ['quote_item_id' => $line->id, 'amount' => 2500]);
    $clone = $this->postJson('/api/v1/quotes/'.$id.'/clone')->assertSuccessful()->json('data.id');
    $cloneLine = Quote::find($clone)->items()->firstOrFail();
    expect($cloneLine->fields()->first()->defaultAnswer)->toBe('Milestone one');
    $this->assertDatabaseHas('taxes', ['quote_item_id' => $cloneLine->id, 'quote_id' => null, 'estimate_item_id' => null]);
    $this->postJson('/api/v1/quotes/delete', ['ids' => [$id]])->assertOk();
    $this->assertDatabaseMissing('taxes', ['quote_item_id' => $line->id]);
    expect($cloneLine->taxes()->count())->toBe(1);
});

test('viewing a sent quote notifies the issuer only once', function () {
    $quote = Quote::factory()->sent()->create(['customer_id' => $this->customer->id, 'expiry_date' => now()->addWeek()->toDateString()]);
    CompanySetting::setSettings(['notify_quote_viewed' => 'YES', 'notification_email' => 'issuer@example.test'], $this->company->id);
    Sanctum::actingAs($this->customer, ['*'], 'customer');
    $path = '/api/v1/'.$this->company->slug.'/customer/quotes/'.$quote->id;
    $this->getJson($path)->assertOk()->assertJsonPath('data.status', 'VIEWED');
    $this->getJson($path)->assertOk();
    Mail::assertSent(QuoteViewedMail::class, 1);
});

test('the quote migration preserves existing company branding and custom email text', function () {
    $migration = require database_path('migrations/2026_09_27_132225_create_quotes_tables.php');
    $migration->down();
    CompanySetting::setSettings([
        'estimate_company_address_format' => '<b>Estimate Experts Ltd</b>',
        'estimate_mail_body' => 'Your estimate from Estimate Experts: {ESTIMATE_NUMBER}',
        'estimate_convert_action' => 'mark_estimate_as_accepted',
    ], $this->company->id);
    $migration->up();
    expect(CompanySetting::getSetting('quote_company_address_format', $this->company->id))->toBe('<b>Estimate Experts Ltd</b>')
        ->and(CompanySetting::getSetting('quote_mail_body', $this->company->id))->toBe('Your estimate from Estimate Experts: {QUOTE_NUMBER}')
        ->and(CompanySetting::getSetting('estimate_mail_body', $this->company->id))->toBe('Your estimate from Estimate Experts: {ESTIMATE_NUMBER}')
        ->and(CompanySetting::getSetting('quote_convert_action', $this->company->id))->toBe('mark_quote_as_accepted');
});

test('creating a quote cannot bypass the separate send permission', function () {
    $payload = quotePayload($this->customer);
    $payload['quoteSend'] = true;
    $this->postJson('/api/v1/quotes', $payload)->assertUnprocessable()->assertJsonValidationErrors('quoteSend');
    $this->assertDatabaseCount('quotes', 0);
    Mail::assertNothingSent();
});

test('quote sorting applies outside the tenant-safe filter group', function () {
    $this->postJson('/api/v1/quotes', quotePayload($this->customer, 'QUO-000001'))->assertCreated();
    $this->postJson('/api/v1/quotes', quotePayload($this->customer, 'QUO-000002'))->assertCreated();
    $this->getJson('/api/v1/quotes?orderByField=quote_number&orderBy=desc')->assertOk()->assertJsonPath('data.0.quote_number', 'QUO-000002');
    $this->getJson('/api/v1/quotes?orderByField=quote_number&orderBy=asc')->assertOk()->assertJsonPath('data.0.quote_number', 'QUO-000001');
});
