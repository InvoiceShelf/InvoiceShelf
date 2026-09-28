<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Pdf\Application\PdfFilenameService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('empty filename preference preserves document-number filenames', function (string $model, string $collection) {
    $document = $model::factory()->create();
    expect(app(PdfFilenameService::class)->filename($document, $collection))
        ->toBe($document->{$collection.'_number'}.'.pdf');
})->with([[Invoice::class, 'invoice'], [Estimate::class, 'estimate'], [Payment::class, 'payment']]);

test('filename formats are validated and stored through the company settings endpoint', function () {
    $user = User::query()->findOrFail(1);
    $companyId = $user->companies()->firstOrFail()->id;
    $format = '{COMPANY_NAME} - {DOCUMENT_NUMBER}';

    Sanctum::actingAs($user, ['*']);
    $this->withHeader('company', $companyId);

    postJson('/api/v1/company/settings', ['settings' => ['pdf_filename_format' => $format]])
        ->assertOk();

    expect(CompanySetting::getSetting('pdf_filename_format', $companyId))->toBe($format);

    postJson('/api/v1/company/settings', ['settings' => ['pdf_filename_format' => str_repeat('x', 256)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.pdf_filename_format');

    expect(CompanySetting::getSetting('pdf_filename_format', $companyId))->toBe($format);
});

test('configured names are shared by invoices estimates and payment receipts', function (string $model, string $collection) {
    $document = $model::factory()->create();
    $document->customer->update(['name' => 'Theater Zeppelin e.V.']);
    $document->company->update(['name' => 'Linus Kurz']);
    CompanySetting::setSettings(['pdf_filename_format' => '{CONTACT_DISPLAY_NAME} - {COMPANY_NAME} - {DOCUMENT_NUMBER}'], $document->company_id);

    expect(app(PdfFilenameService::class)->filename($document, $collection))
        ->toBe('Theater Zeppelin e.V. - Linus Kurz - '.$document->{$collection.'_number'}.'.pdf');
})->with([[Invoice::class, 'invoice'], [Estimate::class, 'estimate'], [Payment::class, 'payment']]);

test('document date component uses each PDF document issue date', function (string $model, string $collection) {
    $dateAttribute = $collection.'_date';
    $document = $model::factory()->create([$dateAttribute => '2026-09-22']);
    CompanySetting::setSettings(
        ['pdf_filename_format' => '{COMPANY_NAME} - {DOCUMENT_DATE} - {DOCUMENT_NUMBER}'],
        $document->company_id
    );

    expect(app(PdfFilenameService::class)->filename($document, $collection))
        ->toContain(' - 2026-09-22 - '.$document->{$collection.'_number'}.'.pdf');
})->with([[Invoice::class, 'invoice'], [Estimate::class, 'estimate'], [Payment::class, 'payment']]);

test('unicode names have an ASCII header fallback and a UTF-8 filename parameter', function () {
    $invoice = Invoice::factory()->create(['invoice_number' => 'INV-UNICODE']);
    $invoice->customer->update(['name' => "Müller & Söhne / Berlin\r\n"]);
    CompanySetting::setSettings(['pdf_filename_format' => '{CONTACT_DISPLAY_NAME} - {DOCUMENT_NUMBER}'], $invoice->company_id);
    $filenames = app(PdfFilenameService::class);
    $name = $filenames->filename($invoice, 'invoice');
    $header = $filenames->disposition($name);

    expect($name)->toBe('Müller & Söhne Berlin - INV-UNICODE.pdf')
        ->and($header)->toContain("filename*=utf-8''", 'M%C3%BCller')
        ->not->toContain("\r", "\n", '/');
});

test('missing placeholders and reserved Windows filenames are safe', function () {
    $invoice = Invoice::factory()->create(['invoice_number' => 'INV-FALLBACK']);
    $invoice->customer->update(['contact_name' => null]);
    CompanySetting::setSettings(['pdf_filename_format' => '{PRIMARY_CONTACT_NAME} - {UNKNOWN} - {DOCUMENT_NUMBER}'], $invoice->company_id);
    expect(app(PdfFilenameService::class)->filename($invoice, 'invoice'))->toBe('INV-FALLBACK.pdf');
    CompanySetting::setSettings(['pdf_filename_format' => 'CON'], $invoice->company_id);
    expect(app(PdfFilenameService::class)->filename($invoice, 'invoice'))->toBe('_CON.pdf');
});

test('cached PDFs use the current preference instead of stale media filenames', function () {
    CompanySetting::setSettings(['pdf_filename_format' => '{COMPANY_NAME} - {DOCUMENT_NUMBER}'], 1);
    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->setRawAttributes(['company_id' => 1, 'invoice_number' => 'INV-CACHED']);
    $invoice->shouldReceive('getFieldsArray')->andReturn(['{COMPANY_NAME}' => 'Linus Kurz']);
    $path = tempnam(sys_get_temp_dir(), 'pdf-filename-');
    file_put_contents($path, '%PDF-cached');
    $invoice->shouldReceive('getGeneratedPDF')->with('invoice')->andReturn(collect(['path' => $path, 'file_name' => 'old.pdf']));

    try {
        $response = $invoice->getGeneratedPDFOrStream('invoice');
        expect($response->getContent())->toBe('%PDF-cached')
            ->and($response->headers->get('Content-Disposition'))->toContain('Linus Kurz - INV-CACHED.pdf');
    } finally {
        unlink($path);
    }
});

test('fresh invoice responses use the configured download name', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['invoice_number' => 'INV-FRESH']);
    CompanySetting::setSettings(['pdf_filename_format' => '{DOCUMENT_NUMBER} - fresh'], $invoice->company_id);
    $response = $invoice->getGeneratedPDFOrStream('invoice');
    expect($response->getContent())->toStartWith('%PDF-')
        ->and($response->headers->get('Content-Disposition'))->toContain('INV-FRESH - fresh.pdf');
});
