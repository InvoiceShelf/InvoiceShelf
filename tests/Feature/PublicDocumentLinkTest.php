<?php

use App\Mail\SendInvoiceMail;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Tax;
use App\Models\User;
use Hashids\Hashids;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * The token InvoiceShelf used to derive from an id. Its salt is a class name
 * followed by APP_KEY, but Hashids reads only the first few dozen characters
 * of a salt, so the key never counted: every installation derived the same
 * token from the same id.
 */
function derivedToken(string $class, int $id): string
{
    $config = config("hashids.connections.{$class}");

    return (new Hashids($class.config('app.key'), $config['length'], $config['alphabet']))->encode($id);
}

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::query()->where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('the token behind a public link does not depend on the installation', function () {
    $config = config('hashids.connections.'.EmailLog::class);
    $salted = fn (string $key): string => (new Hashids(EmailLog::class.$key, $config['length'], $config['alphabet']))->encode(1);

    // Why the old tokens had to go: two unrelated keys, one token.
    expect($salted('base64:'.base64_encode(random_bytes(32))))->toBe($salted('base64:'.base64_encode(random_bytes(32))));
});

test('a new invoice gets a random link token', function () {
    postJson('api/v1/invoices', Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]))->assertOk();

    $invoice = Invoice::query()->latest('id')->first();

    expect($invoice->unique_hash)->toHaveLength(40)
        ->not->toBe(derivedToken(Invoice::class, $invoice->id));
});

test('a sent invoice email gets a random link token', function () {
    $invoice = Invoice::factory()->create();

    (new SendInvoiceMail([
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Your invoice',
        'body' => 'Please find it attached.',
        'invoice' => $invoice->toArray(),
        'attach' => ['data' => null],
    ]))->build();

    $log = EmailLog::query()->latest('id')->firstOrFail();

    expect($log->token)->toHaveLength(40)
        ->not->toBe(derivedToken(EmailLog::class, $log->id));
});

test('upgrading re-issues every token a public link was derived from', function () {
    $invoice = Invoice::factory()->create();
    $invoice->forceFill(['unique_hash' => derivedToken(Invoice::class, $invoice->id)])->save();
    $estimate = Estimate::factory()->create();
    $estimate->forceFill(['unique_hash' => derivedToken(Estimate::class, $estimate->id)])->save();
    $payment = Payment::factory()->create();
    $payment->forceFill(['unique_hash' => derivedToken(Payment::class, $payment->id)])->save();
    $log = EmailLog::query()->create([
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Your invoice',
        'body' => 'Please find it attached.',
        'mailable_type' => Invoice::class,
        'mailable_id' => $invoice->id,
    ]);
    $derived = derivedToken(EmailLog::class, $log->id);
    $log->forceFill(['token' => $derived])->save();

    getJson('/customer/invoices/'.$derived)->assertOk();

    (require database_path('migrations/2026_09_23_100000_reissue_public_link_tokens.php'))->up();

    $issued = $log->fresh()->token;

    expect($issued)->toHaveLength(40)->not->toBe($derived)
        ->and($invoice->fresh()->unique_hash)->toHaveLength(40)
        ->and($estimate->fresh()->unique_hash)->toHaveLength(40)
        ->and($payment->fresh()->unique_hash)->toHaveLength(40);

    getJson('/customer/invoices/'.$derived)->assertNotFound();
    getJson('/customer/invoices/'.$issued)->assertOk();
});
