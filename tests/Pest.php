<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit');

// Module-system tests scaffold, install, and remove real directories under
// Modules. Paratest isolates the database but not that shared filesystem path,
// so every filesystem-mutating module suite runs serially after the parallel
// pass to avoid one worker scanning another worker's staging directory.
uses()->group('modules', 'serial-only')->in(
    'Feature/Admin/Modules',
    'Feature/Company/Modules',
    'Feature/Marketplace',
);

// Architecture assertions parse broad namespace graphs and retain that graph
// for the life of a worker. Run them in the serial phase so an ordinary feature
// test is not handed the parser's memory footprint in the same 128 MB process.
uses()->group('architecture', 'serial-only')->in('Unit/Architecture', 'Feature/Architecture');

/** Replay a historical payment migration against its original schema. */
function withLegacyPaymentStorage(callable $run): mixed
{
    $naming = require database_path('migrations/2026_09_27_190911_rename_customer_payment_storage.php');
    $naming->down();
    try {
        return $run();
    } finally {
        $naming->up();
    }
}

function purchaseFixtures($test): void
{
    $test->seed(DatabaseSeeder::class);
    $test->user = User::where('email', 'admin@invoiceshelf.com')->firstOrFail();
    $test->companyId = (int) $test->user->companies()->firstOrFail()->id;
    $test->currencyId = (int) Currency::where('code', 'USD')->value('id');
    CompanySetting::setSettings(['currency' => $test->currencyId], $test->companyId);
    $test->category = ExpenseCategory::create(['company_id' => $test->companyId, 'name' => 'Hosting']);
    $test->supplier = Supplier::create(['company_id' => $test->companyId, 'creator_id' => $test->user->id, 'name' => 'Acme supplier', 'currency_id' => $test->currencyId, 'payment_terms' => 30]);
    Sanctum::actingAs($test->user);
    $test->withHeaders(['company' => $test->companyId]);
}

function purchaseBillPayload($test, int $amount = 100000): array
{
    return ['supplier_id' => $test->supplier->id, 'currency_id' => $test->currencyId, 'exchange_rate' => 1, 'document_date' => '2026-09-01', 'due_date' => '2026-09-30', 'status' => 'OPEN', 'items' => [['description' => 'Hosting', 'quantity' => 1, 'price' => $amount, 'expense_category_id' => $test->category->id, 'tax_type_ids' => []]]];
}
