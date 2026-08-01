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

    $invoice = Invoice::factory()
        ->hasItems(1)
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
