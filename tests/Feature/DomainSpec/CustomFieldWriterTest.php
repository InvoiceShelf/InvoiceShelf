<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Receivables\Models\Payment;
use Illuminate\Support\Facades\Artisan;

/**
 * The writer takes a field id straight off the request: no form request
 * declares a rule for it, so whatever arrives is what it is asked to honour.
 * These cover what it does with an id it should not honour, and with a
 * definition that has changed since it was last answered.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->writer = app(CustomFieldValueWriter::class);
});

test('an answer naming another company field is ignored', function () {
    $customer = Customer::factory()->create();
    $foreign = CustomField::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'model_type' => 'Customer',
        'type' => 'Input',
    ]);

    $this->writer->attach($customer, [['id' => $foreign->id, 'value' => 'leaked']]);

    expect($customer->fields()->count())->toBe(0);
});

test('an answer naming no field at all is ignored rather than fatal', function (mixed $id) {
    $customer = Customer::factory()->create();

    $this->writer->attach($customer, [['id' => $id, 'value' => 'nowhere']]);

    expect($customer->fields()->count())->toBe(0);
})->with([
    'an id that was deleted' => [999999],
    'no id at all' => [null],
    'something that is not an id' => ['not-a-field'],
]);

test('re-answering a field whose type changed replaces the answer', function () {
    $field = CustomField::factory()->create(['model_type' => 'Customer', 'type' => 'Input']);
    $customer = Customer::factory()->create(['company_id' => $field->company_id]);

    $this->writer->attach($customer, [['id' => $field->id, 'value' => 'typed as text']]);

    // The definition is edited after the fact, which moves the answer to a
    // different column. The existing row should follow it, not be joined by
    // a second one still holding the old column.
    $field->update(['type' => 'Number']);

    $this->writer->update($customer, [['id' => $field->id, 'value' => 42]]);

    expect($customer->fields()->count())->toBe(1)
        ->and($customer->fields()->sole()->number_answer)->toBe(42);
});

test('deleting a payment takes its answers with it', function () {
    $field = CustomField::factory()->create(['model_type' => 'Payment', 'type' => 'Input']);
    $payment = Payment::factory()->create(['company_id' => $field->company_id]);

    $this->writer->attach($payment, [['id' => $field->id, 'value' => 'paid in cash']]);
    expect($payment->fields()->count())->toBe(1);

    $payment->delete();

    $this->assertDatabaseMissing('custom_field_values', ['custom_field_id' => $field->id]);
});

test('a definition stores its options as a list and can clear a time default', function () {
    $field = CustomField::factory()->create([
        'model_type' => 'Customer',
        'type' => 'Dropdown',
        'options' => ['One', 'Two'],
    ]);

    expect($field->fresh()->options)->toBe(['One', 'Two']);

    $field->update(['type' => 'Time', 'time_answer' => '09:30']);
    expect($field->fresh()->time_answer)->toBe('09:30:00');

    $field->update(['time_answer' => null]);
    expect($field->fresh()->time_answer)->toBeNull();
});
