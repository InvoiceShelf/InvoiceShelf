<?php

use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * A document's template_name is validated when saved through the UI, but
 * seeders, imports, recurring-invoice copies and rows predating that validation
 * all bypass it. An unresolvable name used to reach `$template['custom']` on
 * null and take the PDF route down with a 500 — which is exactly what the demo
 * estimates did, since the seeder never set the field at all.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);

    Storage::fake('public');
    config(['pdf.driver' => 'dompdf']);
});

test('a usable template resolves to itself', function () {
    expect(PdfTemplateUtils::resolveView('invoice', 'invoice2', 'invoice1'))
        ->toBe('app.pdf.invoice.invoice2');
});

test('an unusable template falls back to the default design', function (?string $stored) {
    expect(PdfTemplateUtils::resolveView('estimate', $stored, 'estimate1'))
        ->toBe('app.pdf.estimate.estimate1');
})->with([
    'empty' => '',
    'null' => null,
    'nonsense' => 'no-such-template',
]);

test('a custom template still wins over the built-in of the same name', function () {
    Storage::fake('pdf_templates');

    $dir = Storage::disk('pdf_templates')->path('invoice');
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/invoice1.blade.php", '<html></html>');

    // The disk and the view namespace are registered separately and only the
    // disk is faked, so point the namespace at the same place.
    View::addNamespace('pdf_templates', Storage::disk('pdf_templates')->path(''));
    View::getFinder()->flush();

    expect(PdfTemplateUtils::resolveView('invoice', 'invoice1', 'invoice1'))
        ->toBe('pdf_templates::invoice.invoice1');
});

/**
 * The regression that started this: every demo estimate had an empty
 * template_name, so its PDF route 500'd on either driver.
 */
test('an estimate with no template still renders', function (?string $stored) {
    $estimate = Estimate::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => $stored,
    ]);

    $response = get("/estimates/pdf/{$estimate->unique_hash}");

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
})->with([
    'empty' => '',
    'nonsense' => 'deleted-by-an-admin',
]);

test('an invoice with no template still renders', function () {
    $invoice = Invoice::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => '',
    ]);

    $response = get("/invoices/pdf/{$invoice->unique_hash}");

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
});

/**
 * The seeder itself: seedInvoice() has always set template_name, seedEstimate()
 * never did. Guard the data rather than only the render path.
 */
test('every seeded document has a template that resolves', function () {
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    $invoices = array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name');
    $estimates = array_column(PdfTemplateUtils::getFormattedTemplates('estimate', ''), 'name');

    expect(Invoice::pluck('template_name')->unique()->filter()->all())->each->toBeIn($invoices);
    expect(Estimate::pluck('template_name')->unique()->all())->each->toBeIn($estimates);
});
