<?php

namespace Tests\Feature\Customer;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * A definition left `internal` is the private staff note #327 asked for. The
 * portal serialised every answer regardless of placement, so that note
 * reached the person the invoice was sent to, which is the opposite of what
 * the flag is for.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->customer = Customer::factory()->create();

    Sanctum::actingAs($this->customer, ['*'], 'customer');
});

test('the portal shows a printed answer and withholds an internal one', function () {
    $printed = CustomField::factory()->create([
        'company_id' => $this->customer->company_id,
        'model_type' => 'Invoice',
        'type' => 'Input',
        'placement' => 'document',
    ]);

    $internal = CustomField::factory()->create([
        'company_id' => $this->customer->company_id,
        'model_type' => 'Invoice',
        'type' => 'Input',
        'placement' => 'internal',
    ]);

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'company_id' => $this->customer->company_id,
    ]);

    app(CustomFieldValueWriter::class)->attach($invoice, [
        ['id' => $printed->id, 'value' => 'shown to the customer'],
        ['id' => $internal->id, 'value' => 'chased twice, pays late'],
    ]);

    $company = Auth::guard('customer')->user()->company;

    $body = getJson("/api/v1/{$company->slug}/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->getContent();

    expect($body)->toContain('shown to the customer')
        ->and($body)->not->toContain('chased twice, pays late');
});
