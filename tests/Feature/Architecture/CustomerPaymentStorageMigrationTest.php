<?php

use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Platform\Persistence\ModelIdentityMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('customer payment storage names are explicit while public identities remain stable', function () {
    expect((new Payment)->getTable())->toBe('customer_payments')
        ->and((new PaymentAllocation)->getTable())->toBe('customer_payment_allocations')
        ->and((new Payment)->getMorphClass())->toBe('customer_payment')
        ->and((new PaymentAllocation)->getMorphClass())->toBe('customer_payment_allocation')
        ->and(ModelIdentityMap::publicType('customer_payment'))->toBe('App\\Models\\Payment')
        ->and(Schema::hasTable('payments'))->toBeFalse();
});

test('payment storage upgrade preserves ids and migrates authorization identities in both directions', function () {
    $migration = require database_path('migrations/2026_09_27_190911_rename_customer_payment_storage.php');
    $migration->down();
    $id = DB::table('abilities')->insertGetId(['name' => 'payment-naming-test', 'entity_type' => 'payment', 'entity_id' => 42, 'only_owned' => false]);
    $permission = DB::table('permissions')->insertGetId(['ability_id' => $id, 'entity_id' => 42, 'entity_type' => 'payment_allocation', 'forbidden' => false]);
    $migration->up();
    $migration->up();
    expect(DB::table('abilities')->where('id', $id)->value('entity_type'))->toBe('customer_payment')
        ->and(DB::table('permissions')->where('id', $permission)->value('entity_type'))->toBe('customer_payment_allocation');
    $migration->down();
    expect(DB::table('abilities')->where('id', $id)->value('entity_type'))->toBe('payment');
    $migration->up();
});

test('renaming customer receipts preserves mail media custom answers and public links', function () {
    purchaseFixtures($this);
    Queue::fake();
    Storage::fake(config('media-library.disk_name'));
    $customer = Customer::create(['company_id' => $this->companyId, 'name' => 'Receipt customer', 'currency_id' => $this->currencyId]);
    $payment = Payment::create(['company_id' => $this->companyId, 'customer_id' => $customer->id, 'creator_id' => $this->user->id, 'currency_id' => $this->currencyId, 'payment_number' => 'PAY-NAMING', 'payment_date' => '2026-09-01', 'amount' => 1234, 'base_amount' => 1234, 'exchange_rate' => 1, 'unique_hash' => str_repeat('a', 40)]);
    $mail = $payment->emailLogs()->create(['from' => 'test@example.test', 'to' => 'customer@example.test', 'subject' => 'Receipt', 'body' => 'Received']);
    $field = CustomField::create(['company_id' => $this->companyId, 'name' => 'Receipt note', 'slug' => 'receipt_note', 'label' => 'Receipt note', 'model_type' => 'Payment', 'type' => 'Input', 'order' => 1]);
    $answer = $payment->fields()->create(['company_id' => $this->companyId, 'custom_field_id' => $field->id, 'type' => 'Input', 'string_answer' => 'Original answer']);
    $media = $payment->addMedia(UploadedFile::fake()->create('receipt.pdf', 1, 'application/pdf'))->toMediaCollection('receipts');
    $url = $payment->paymentPdfUrl;
    $storedPath = $media->getPathRelativeToRoot();
    $migration = require database_path('migrations/2026_09_27_190911_rename_customer_payment_storage.php');
    $migration->down();
    expect(DB::table('payments')->where('id', $payment->id)->value('amount'))->toBe(1234)
        ->and(DB::table('email_logs')->where('id', $mail->id)->value('mailable_type'))->toBe('payment');
    $migration->up();
    $payment->refresh();
    expect($payment->paymentPdfUrl)->toBe($url)
        ->and($payment->emailLogs()->sole()->id)->toBe($mail->id)
        ->and($payment->fields()->sole()->id)->toBe($answer->id)
        ->and($payment->media()->sole()->id)->toBe($media->id)
        ->and($mail->fresh()->mailable->id)->toBe($payment->id)
        ->and($field->fresh()->model_type)->toBe('Payment')
        ->and($payment->media()->sole()->getPathRelativeToRoot())->toBe($storedPath);
    Storage::disk($media->disk)->assertExists($storedPath);
});
