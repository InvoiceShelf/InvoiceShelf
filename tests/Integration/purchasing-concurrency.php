<?php

/** Run only against the disposable purchasing-test-{mysql,postgres} containers. */

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$config = config('database.connections.'.$connection);
if (! in_array($config['host'], ['purchasing-test-mysql', 'purchasing-test-postgres'], true) || $config['database'] !== 'purchasing_test') {
    throw new RuntimeException('This test requires its disposable database container.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
$user = User::where('email', 'admin@invoiceshelf.com')->firstOrFail();
$company = $user->companies()->firstOrFail();
$currency = (int) Currency::where('code', 'USD')->value('id');
CompanySetting::setSettings(['currency' => $currency], $company->id);
$supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Concurrent supplier', 'currency_id' => $currency]);
$category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Concurrent costs']);
$payload = ['supplier_id' => $supplier->id, 'currency_id' => $currency, 'exchange_rate' => 1, 'document_date' => '2026-09-01', 'due_date' => '2026-09-30', 'status' => 'OPEN', 'items' => [['description' => 'Concurrent bill', 'quantity' => 1, 'price' => 100, 'expense_category_id' => $category->id]]];
$bill = app(PurchaseDocumentService::class)->saveBill(null, $company->id, $user->id, $payload);

function race(callable $operation): array
{
    DB::disconnect();
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($pair[0]);
            fread($pair[1], 1);
            DB::purge();
            try {
                $value = $operation($i);
                $result = ['ok' => true, 'value' => $value];
            } catch (ValidationException $error) {
                $result = ['ok' => false, 'validation' => true];
            } catch (Throwable $error) {
                $result = ['ok' => false, 'error' => $error->getMessage()];
            }
            fwrite($pair[1], json_encode($result));
            fclose($pair[1]);
            exit(0);
        }
        fclose($pair[1]);
        $children[] = [$pid, $pair[0]];
    }
    foreach ($children as [$pid, $socket]) {
        fwrite($socket, 'G');
    }
    $results = [];
    foreach ($children as [$pid, $socket]) {
        $results[] = json_decode(stream_get_contents($socket), true);
        fclose($socket);
        pcntl_waitpid($pid, $status);
    }
    DB::purge();
    foreach ($results as $result) {
        if (isset($result['error'])) {
            throw new RuntimeException($result['error']);
        }
    }

    return $results;
}

function singleWinner(array $results, string $label): void
{
    if (count(array_filter($results, fn ($result) => $result['ok'])) !== 1) {
        throw new RuntimeException($label.' did not serialize correctly: '.json_encode($results));
    }
    echo $label.": passed\n";
}

singleWinner(race(fn () => app(SupplierSettlementService::class)->recordPayment($company->id, $user->id, ['supplier_id' => $supplier->id, 'currency_id' => $currency, 'exchange_rate' => 1, 'amount' => 100, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill->id, 'amount' => 100]]])->id), 'Concurrent bill payments');
$advance = app(SupplierSettlementService::class)->recordPayment($company->id, $user->id, ['supplier_id' => $supplier->id, 'currency_id' => $currency, 'exchange_rate' => 1, 'amount' => 100, 'payment_date' => '2026-09-02']);
singleWinner(race(fn () => app(SupplierSettlementService::class)->recordRefund($company->id, $user->id, ['supplier_payment_id' => $advance->id, 'amount' => 80, 'exchange_rate' => 1, 'payment_date' => '2026-09-03'])->id), 'Concurrent refunds');
$creditInput = $payload;
$creditInput['source_bill_id'] = $bill->id;
$creditInput['items'][0]['source_bill_item_id'] = $bill->items->first()->id;
$creditInput['items'][0]['quantity'] = 0.75;
singleWinner(race(fn () => app(PurchaseDocumentService::class)->createCredit($company->id, $user->id, $creditInput)->id), 'Concurrent supplier credits');

$otherSupplier = Supplier::create(['company_id' => $company->id, 'name' => 'Corrected supplier', 'currency_id' => $currency]);
$editable = app(PurchaseDocumentService::class)->saveBill(null, $company->id, $user->id, $payload);
singleWinner(race(fn (int $worker) => $worker === 0
    ? app(PurchaseDocumentService::class)->saveBill($editable, $company->id, $user->id, [...$payload, 'supplier_id' => $otherSupplier->id])->id
    : app(SupplierSettlementService::class)->recordPayment($company->id, $user->id, ['supplier_id' => $supplier->id, 'currency_id' => $currency, 'exchange_rate' => 1, 'amount' => 100, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $editable->id, 'amount' => 100]]])->id), 'Supplier correction versus payment');
