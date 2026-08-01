<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

getJson('api/v1/dashboard')->assertOk();

getJson('api/v1/search?name=ab')->assertOk();

test('the invoice count excludes credit notes while the sales total nets them out', function () {
    $before = getJson('api/v1/dashboard')->assertOk();

    $baselineCount = $before->json('total_invoice_count');
    $baselineSales = (int) $before->json('total_sales');

    // The line item carries the whole invoice: a credit note is derived from
    // the invoice's own figures, so its total only nets the sale out when the
    // items agree with the document totals, as they do on a real invoice.
    $invoice = Invoice::factory()
        ->hasItems(1, [
            'price' => 10000,
            'quantity' => 1,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'exchange_rate' => 1,
            'base_price' => 10000,
            'base_total' => 10000,
            'base_tax' => 0,
            'base_discount_val' => 0,
        ])
        ->create([
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => now()->format('Y-m-d'),
            'sub_total' => 10000,
            'total' => 10000,
            'base_total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
            'base_due_amount' => 10000,
            'exchange_rate' => 1,
        ]);

    postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(201);

    $after = getJson('api/v1/dashboard')->assertOk();

    // One invoice was issued, and one reversal of it exists. "Invoices" counts
    // the issued document only.
    expect($after->json('total_invoice_count'))->toBe($baselineCount + 1);

    // The sums deliberately keep credit notes: the negated total is exactly
    // what takes the reversed sale back out of the figure.
    expect((int) $after->json('total_sales'))->toBe($baselineSales);
});
