<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use App\Platform\Mail\Contracts\EmailLogWriter;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Persistence\ModelIdentityMap;
use Hashids\Hashids;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * The token InvoiceShelf used to derive from an id. Its salt is a class name
 * followed by APP_KEY, but Hashids reads only the first few dozen characters
 * of a salt, so the key never counted: every installation derived the same
 * token from the same id.
 */
function derivedToken(string $class, string $alphabet, int $id): string
{
    return (new Hashids($class.config('app.key'), 20, $alphabet))->encode($id);
}

const EMAIL_LOG_ALPHABET = 'BA5tJUVNPe93fCq6DHlY2x4ZO1Kg7i8wSm0R';

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $user = User::query()->find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('the token behind a public link does not depend on the installation', function () {
    $salted = fn (string $key): string => (new Hashids('App\\Models\\EmailLog'.$key, 20, EMAIL_LOG_ALPHABET))->encode(1);

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
        ->not->toBe(derivedToken('App\\Models\\Invoice', 'XKAR7m8jD2bqP9OSVeNGiYL465T10zhfWuc3', $invoice->id));
});

test('an email log gets a random link token', function () {
    $invoice = Invoice::factory()->create();

    $token = app(EmailLogWriter::class)->record($invoice, [
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Your invoice',
        'body' => 'Please find it attached.',
    ]);
    $log = EmailLog::query()->where('token', $token)->firstOrFail();

    expect($token)->toHaveLength(40)
        ->not->toBe(derivedToken('App\\Models\\EmailLog', EMAIL_LOG_ALPHABET, $log->id));
});

test('upgrading re-issues every token a public link was derived from', function () {
    $invoice = Invoice::factory()->create();
    $invoice->forceFill(['unique_hash' => derivedToken('App\\Models\\Invoice', 'XKAR7m8jD2bqP9OSVeNGiYL465T10zhfWuc3', $invoice->id)])->save();
    $estimate = Estimate::factory()->create();
    $estimate->forceFill(['unique_hash' => derivedToken('App\\Models\\Estimate', 'yJW2P79M8rCHsVq5zbn1fXl6IUt3dAekGo40', $estimate->id)])->save();
    $payment = Payment::factory()->create();
    $payment->forceFill(['unique_hash' => derivedToken('App\\Models\\Payment', 'aqW3eR2Icf0jp65Gl7UVS1dhyb8Mn9XKTZ4O', $payment->id)])->save();
    $log = EmailLog::query()->create([
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Your invoice',
        'body' => 'Please find it attached.',
        'mailable_type' => ModelIdentityMap::aliasFor(Invoice::class),
        'mailable_id' => $invoice->id,
    ]);
    $derived = derivedToken('App\\Models\\EmailLog', EMAIL_LOG_ALPHABET, $log->id);
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
    get('/invoices/pdf/'.$invoice->fresh()->unique_hash.'?preview')->assertOk();
});
