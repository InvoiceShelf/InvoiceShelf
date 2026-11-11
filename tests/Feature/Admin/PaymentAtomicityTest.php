<?php

use App\Jobs\GeneratePaymentPdfJob;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Bus::fake();
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);

    $this->invoice = Invoice::factory()->create([
        'recurring_invoice_id' => null,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 10000,
        'due_amount' => 10000,
        'base_total' => 10000,
        'base_due_amount' => 10000,
        'exchange_rate' => 1,
    ]);
    $this->payload = Payment::factory()->raw([
        'invoice_id' => $this->invoice->id,
        'customer_id' => $this->invoice->customer_id,
        'currency_id' => $this->invoice->currency_id,
        'amount' => 4000,
        'exchange_rate' => 1,
        'notes' => 'PAYMENT_ATOMICITY_TEST',
    ]);
});

function paymentAtomicitySnapshot(): array
{
    return collect(['invoices', 'payments', 'exchange_rate_logs', 'custom_field_values'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])
        ->all();
}

function failPaymentAtomicityEvent(string $event, string $model, ?int $id = null): void
{
    Event::listen("eloquent.{$event}: {$model}", function ($record) use ($id) {
        if ($id === null || (int) $record->id === $id) {
            throw new RuntimeException('PAYMENT_ATOMICITY_TEST');
        }
    });
}

test('a failed payment write rolls back every financial row', function ($operation, $event, $sqlFailure) {
    if ($sqlFailure && DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The SQL fault trigger uses SQLite syntax; event fault tests cover every backend.');
    }

    $paymentId = null;
    if ($operation !== 'create') {
        $paymentId = $this->postJson('/api/v1/payments', $this->payload)
            ->assertOk()->json('data.id');
    }

    if ($sqlFailure) {
        $sqlEvent = ['creating' => 'INSERT', 'updating' => 'UPDATE', 'deleting' => 'DELETE'][$event];
        $row = $operation === 'delete' ? 'OLD' : 'NEW';
        DB::unprepared("CREATE TRIGGER payment_atomicity_fault BEFORE {$sqlEvent} ON payments
            WHEN {$row}.notes = 'PAYMENT_ATOMICITY_TEST'
            BEGIN SELECT RAISE(ABORT, 'PAYMENT_ATOMICITY_TEST'); END");
    } else {
        failPaymentAtomicityEvent($event, Payment::class, $paymentId);
    }

    $before = paymentAtomicitySnapshot();
    try {
        $response = match ($operation) {
            'create' => $this->postJson('/api/v1/payments', $this->payload),
            'update' => $this->putJson("/api/v1/payments/{$paymentId}", array_merge($this->payload, ['amount' => 2500])),
            'delete' => $this->postJson('/api/v1/payments/delete', ['ids' => [$paymentId]]),
        };
        $response->assertStatus(500)->assertSee('PAYMENT_ATOMICITY_TEST');
        expect(paymentAtomicitySnapshot())->toBe($before);
    } finally {
        if ($sqlFailure) {
            DB::unprepared('DROP TRIGGER payment_atomicity_fault');
        }
    }
})->with([
    'create event failure' => ['create', 'creating', false],
    'update event failure' => ['update', 'updating', false],
    'delete event failure' => ['delete', 'deleting', false],
    'create SQL failure' => ['create', 'creating', true],
    'update SQL failure' => ['update', 'updating', true],
    'delete SQL failure' => ['delete', 'deleting', true],
]);

test('a failed reassignment restores both invoice balances and the original payment link', function () {
    $paymentId = $this->postJson('/api/v1/payments', $this->payload)->assertOk()->json('data.id');
    $target = Invoice::factory()->create([
        'recurring_invoice_id' => null,
        'customer_id' => $this->invoice->customer_id,
        'total' => 10000,
        'due_amount' => 10000,
        'base_total' => 10000,
        'base_due_amount' => 10000,
        'exchange_rate' => 1,
    ]);
    failPaymentAtomicityEvent('updating', Payment::class, $paymentId);
    $before = paymentAtomicitySnapshot();

    $this->putJson("/api/v1/payments/{$paymentId}", array_merge($this->payload, ['invoice_id' => $target->id]))
        ->assertStatus(500)->assertSee('PAYMENT_ATOMICITY_TEST');

    expect(paymentAtomicitySnapshot())->toBe($before);
});

test('a failure deleting the second payment rolls back the whole batch', function () {
    $firstId = $this->postJson('/api/v1/payments', $this->payload)->assertOk()->json('data.id');
    $secondId = $this->postJson('/api/v1/payments', array_merge($this->payload, [
        'payment_number' => 'PAY-ATOMIC-SECOND',
        'amount' => 2500,
    ]))->assertOk()->json('data.id');
    failPaymentAtomicityEvent('deleting', Payment::class, $secondId);
    $before = paymentAtomicitySnapshot();

    $this->postJson('/api/v1/payments/delete', ['ids' => [$firstId, $secondId]])
        ->assertStatus(500)->assertSee('PAYMENT_ATOMICITY_TEST');

    expect(paymentAtomicitySnapshot())->toBe($before);
});

test('a failure assigning the payment sequence rolls back the inserted payment and invoice', function () {
    failPaymentAtomicityEvent('updating', Payment::class);
    $before = paymentAtomicitySnapshot();

    $this->postJson('/api/v1/payments', $this->payload)
        ->assertStatus(500)->assertSee('PAYMENT_ATOMICITY_TEST');

    expect(paymentAtomicitySnapshot())->toBe($before);
});

test('a late custom field failure rolls back the payment, exchange log and earlier fields', function ($operation) {
    $fields = CustomField::factory()->count(2)->create(['model_type' => 'Payment', 'type' => 'Text']);
    $payload = array_merge($this->payload, [
        'currency_id' => 2,
        'exchange_rate' => 1.125,
        'customFields' => $fields->map(fn ($field) => ['id' => $field->id, 'value' => 'before'])->all(),
    ]);
    // A foreign-currency customer causes an exchange-rate row before field writes.
    $this->invoice->customer->update(['currency_id' => 2]);
    $paymentId = $operation === 'update'
        ? $this->postJson('/api/v1/payments', $payload)->assertOk()->json('data.id')
        : null;

    $lastFieldId = $fields->last()->id;
    Event::listen('eloquent.saving: '.CustomFieldValue::class, function ($value) use ($lastFieldId) {
        if ((int) $value->custom_field_id === $lastFieldId) {
            throw new RuntimeException('PAYMENT_ATOMICITY_TEST');
        }
    });
    $before = paymentAtomicitySnapshot();
    $payload['amount'] = 2500;
    $payload['customFields'] = $fields->map(fn ($field) => ['id' => $field->id, 'value' => 'after'])->all();

    $response = $operation === 'create'
        ? $this->postJson('/api/v1/payments', $payload)
        : $this->putJson("/api/v1/payments/{$paymentId}", $payload);
    $response->assertStatus(500)->assertSee('PAYMENT_ATOMICITY_TEST');

    expect(paymentAtomicitySnapshot())->toBe($before);
})->with(['create', 'update']);

test('successful create update reassignment unlink and delete still preserve draft workflows', function () {
    $draft = Invoice::factory()->create([
        'recurring_invoice_id' => null,
        'customer_id' => $this->invoice->customer_id,
        'status' => Invoice::STATUS_DRAFT,
        'sent' => false,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 10000,
        'due_amount' => 10000,
        'base_total' => 10000,
        'base_due_amount' => 10000,
        'exchange_rate' => 1,
    ]);
    $paymentId = $this->postJson('/api/v1/payments', $this->payload)->assertOk()->json('data.id');
    $this->putJson("/api/v1/payments/{$paymentId}", array_merge($this->payload, [
        'invoice_id' => $draft->id,
        'amount' => 2500,
    ]))->assertOk();
    $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id, 'due_amount' => 10000, 'base_due_amount' => 10000]);
    $this->assertDatabaseHas('invoices', ['id' => $draft->id, 'due_amount' => 7500, 'base_due_amount' => 7500, 'status' => Invoice::STATUS_DRAFT]);

    $this->putJson("/api/v1/payments/{$paymentId}", array_merge($this->payload, ['invoice_id' => null]))->assertOk();
    $this->assertDatabaseHas('invoices', ['id' => $draft->id, 'due_amount' => 10000, 'base_due_amount' => 10000, 'status' => Invoice::STATUS_DRAFT]);
    $this->postJson('/api/v1/payments/delete', ['ids' => [$paymentId]])->assertOk();
    $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
});

test('payment PDF jobs wait for the financial transaction to commit', function () {
    $paymentId = $this->postJson('/api/v1/payments', $this->payload)->assertOk()->json('data.id');
    $this->putJson("/api/v1/payments/{$paymentId}", array_merge($this->payload, ['amount' => 2500]))->assertOk();

    Bus::assertDispatchedTimes(GeneratePaymentPdfJob::class, 3);
    Bus::assertNotDispatched(GeneratePaymentPdfJob::class, fn ($job) => ! ($job->afterCommit ?? false));
});
