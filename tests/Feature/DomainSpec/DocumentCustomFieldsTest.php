<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * Invoice, estimate, payment and expense forms now submit custom-field
 * answers, which nothing on those paths covered before. The services accepted
 * them all along; these pin the contract the forms rely on, including the
 * expense route, where a multipart client sends the array as a JSON string.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

function fieldFor(string $model): CustomField
{
    return CustomField::factory()->create([
        'company_id' => test()->company->id,
        'model_type' => $model,
        'type' => 'Input',
    ]);
}

test('an invoice keeps the answers it was sent', function () {
    $field = fieldFor('Invoice');

    $payload = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
        'customFields' => [['id' => $field->id, 'value' => 'from the invoice form']],
    ]);

    postJson('api/v1/invoices', $payload)->assertOk();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'from the invoice form',
    ]);
});

test('an estimate keeps the answers it was sent', function () {
    $field = fieldFor('Estimate');

    $payload = Estimate::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
        'customFields' => [['id' => $field->id, 'value' => 'from the estimate form']],
    ]);

    postJson('api/v1/estimates', $payload)->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'from the estimate form',
    ]);
});

test('a payment keeps the answers it was sent', function () {
    $field = fieldFor('Payment');

    $invoice = Invoice::factory()->create([
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'sub_total' => 100,
        'total' => 100,
        'due_amount' => 100,
        'base_sub_total' => 100,
        'base_total' => 100,
        'base_due_amount' => 100,
        'exchange_rate' => 1,
    ]);

    $payload = Payment::factory()->raw([
        'customer_id' => $invoice->customer_id,
        'currency_id' => $invoice->currency_id,
        'amount' => $invoice->due_amount,
        'exchange_rate' => 1,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => $invoice->due_amount]],
        'customFields' => [['id' => $field->id, 'value' => 'from the payment form']],
    ]);

    postJson('api/v1/payments', $payload)->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'from the payment form',
    ]);
});

test('an expense keeps answers sent as the JSON string a multipart form posts', function () {
    $field = fieldFor('Expense');

    // The expense form uploads a receipt, so it posts multipart and every
    // nested value arrives JSON-encoded rather than as an array.
    $payload = Expense::factory()->raw([
        'customFields' => json_encode([['id' => $field->id, 'value' => 'from the expense form']]),
    ]);

    postJson('api/v1/expenses', $payload)->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'from the expense form',
    ]);
});
