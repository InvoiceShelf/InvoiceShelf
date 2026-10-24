<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\EstimateItem;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;

/**
 * With tax per item, the PDF data folds every line's tax rows into one summary
 * row per tax type. It used to fold them into the first line's own row, so the
 * loaded line tax grew by the document total on every render: a second render
 * of the same instance (pdf:compare, a mail after a download, anything reading
 * the items afterwards) printed and read inflated amounts.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    Sanctum::actingAs($user, ['*']);

    $this->taxType = TaxType::factory()->create(['company_id' => $this->company->id, 'percent' => 10]);

    // Ask for the HTML preview so the test does not render a PDF.
    request()->query->set('preview', '1');
});

/**
 * @param  class-string<InvoiceItem|EstimateItem>  $itemClass
 */
function addTaxedLines(Invoice|Estimate $document, string $itemClass, string $foreignKey): void
{
    foreach ([100, 250] as $amount) {
        $item = $itemClass::factory()->create([
            $foreignKey => $document->id,
            'company_id' => $document->company_id,
        ]);

        Tax::factory()->create([
            $foreignKey === 'invoice_id' ? 'invoice_item_id' : 'estimate_item_id' => $item->id,
            'tax_type_id' => test()->taxType->id,
            'company_id' => $document->company_id,
            'amount' => $amount,
        ]);
    }
}

test('rendering an invoice twice leaves its line taxes and summary unchanged', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'tax_per_item' => 'YES',
        'template_name' => 'invoice1',
    ]);
    addTaxedLines($invoice, InvoiceItem::class, 'invoice_id');
    $invoice->load('items.taxes');

    foreach ([1, 2] as $render) {
        app(InvoiceService::class)->getPdfData($invoice);

        expect(View::shared('taxes'))->toHaveCount(1)
            ->and(View::shared('taxes')->first()->amount)->toBe(350, "summary on render {$render}");
    }

    expect($invoice->items->flatMap->taxes->pluck('amount')->sort()->values()->all())->toBe([100, 250]);
});

test('rendering an estimate twice leaves its line taxes and summary unchanged', function () {
    $estimate = Estimate::factory()->create([
        'company_id' => $this->company->id,
        'tax_per_item' => 'YES',
        'template_name' => 'estimate1',
    ]);
    addTaxedLines($estimate, EstimateItem::class, 'estimate_id');
    $estimate->load('items.taxes');

    foreach ([1, 2] as $render) {
        app(EstimateService::class)->getPdfData($estimate);

        expect(View::shared('taxes'))->toHaveCount(1)
            ->and(View::shared('taxes')->first()->amount)->toBe(350, "summary on render {$render}");
    }

    expect($estimate->items->flatMap->taxes->pluck('amount')->sort()->values()->all())->toBe([100, 250]);
});
