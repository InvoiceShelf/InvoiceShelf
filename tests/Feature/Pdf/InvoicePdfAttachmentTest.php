<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Application\InvoiceService;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Operations\Models\Setting;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\FacturXAttachment;
use App\Platform\Pdf\Rendering\PdfDriver;
use App\Platform\Pdf\Rendering\PdfPageSetup;
use App\Platform\Pdf\Rendering\ResponseStream;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;
use InvoiceShelf\Modules\Pdf\Contracts\InvoicePdfAttachmentProvider;
use InvoiceShelf\Modules\Pdf\Data\FacturXAttachment as ModuleFacturXAttachment;
use InvoiceShelf\Modules\Registry;
use Tests\Support\InvoiceDocumentScenario;

use function Pest\Laravel\get;

/**
 * Module API 1.5: an enabled module's InvoicePdfAttachmentProvider supplies
 * the XML embedded in every invoice PDF, on every delivery path. The PDF
 * driver is replaced by a recorder; no Gotenberg is involved.
 */
final class RecordingPdfDriver implements PdfDriver
{
    /** @var list<FacturXAttachment|null> */
    public array $attachments = [];

    /** How many renders that carry an attachment to refuse, as a PDF service rejecting it would. */
    public int $rejectAttachments = 0;

    public function loadView(string $template, array $metadata = [], ?PdfPageSetup $page = null, ?FacturXAttachment $attachment = null): ResponseStream
    {
        if ($attachment !== null && $this->rejectAttachments-- > 0) {
            throw new RuntimeException('Gotenberg rejected the Factur-X fields');
        }

        $this->attachments[] = $attachment;

        return new class implements ResponseStream
        {
            public function stream(string $filename = 'document.pdf'): Response
            {
                return response()->make($this->output(), 200, ['Content-Type' => 'application/pdf']);
            }

            public function download(string $filename = 'document.pdf'): Response
            {
                return $this->stream($filename);
            }

            public function output(): string
            {
                return '%PDF-1.7 recorded';
            }
        };
    }
}

final class FakePdfCapabilities implements PdfCapabilities
{
    public int $asked = 0;

    public function __construct(public bool $capable = true) {}

    public function embedsFacturX(): bool
    {
        $this->asked++;

        return $this->capable;
    }
}

/** A module's provider; its behaviour is set per test through the statics. */
final class FakeAttachmentProvider implements InvoicePdfAttachmentProvider
{
    public static bool $applies = true;

    public static bool $throws = false;

    public function appliesTo(int $companyId): bool
    {
        return self::$applies;
    }

    public function attachmentFor(int $companyId, int $invoiceId): ?ModuleFacturXAttachment
    {
        if (self::$throws) {
            throw new RuntimeException('module failure');
        }

        return new ModuleFacturXAttachment("<rsm:CrossIndustryInvoice id=\"{$invoiceId}\"/>");
    }
}

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->company = User::find(1)->companies()->first();
    $this->invoice = InvoiceDocumentScenario::seed($this->company);

    CompanySetting::setSettings([
        'invoice_email_attachment' => 'YES',
        'notify_invoice_viewed' => 'NO',
    ], $this->company->id);

    $this->recorder = new RecordingPdfDriver;
    Pdf::swap($this->recorder);

    $this->capabilities = new FakePdfCapabilities;
    app()->instance(PdfCapabilities::class, $this->capabilities);

    FakeAttachmentProvider::$applies = true;
    FakeAttachmentProvider::$throws = false;
    Registry::registerInvoicePdfAttachmentProvider('e-invoicing', FakeAttachmentProvider::class);
});

afterEach(function () {
    Registry::flushDrivers();
});

test('an enabled provider\'s xml is embedded in the invoice pdf', function () {
    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toHaveCount(1)
        ->and($this->recorder->attachments[0])->toBeInstanceOf(FacturXAttachment::class)
        ->and($this->recorder->attachments[0]->xml)->toBe("<rsm:CrossIndustryInvoice id=\"{$this->invoice->id}\"/>")
        ->and($this->recorder->attachments[0]->profile)->toBe('EN 16931');
});

test('without a provider the pdf is ordinary and the pdf service is never probed', function () {
    Registry::flushDrivers();

    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toBe([null])
        ->and($this->capabilities->asked)->toBe(0);
});

test('a pdf service that cannot embed gets the ordinary pdf', function () {
    $this->capabilities->capable = false;

    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toBe([null]);
});

test('a provider that does not apply to the company leaves the pdf ordinary', function () {
    FakeAttachmentProvider::$applies = false;

    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toBe([null]);
});

test('a failing provider is reported and the invoice still renders', function () {
    Exceptions::fake();
    FakeAttachmentProvider::$throws = true;

    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toBe([null]);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'module failure');
});

test('a pdf service that rejects the attachment is reported and the ordinary pdf goes out', function () {
    Exceptions::fake();
    $this->recorder->rejectAttachments = 1;

    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toBe([null]);
    Exceptions::assertReported(RuntimeException::class);
});

test('the html preview never asks for an attachment', function () {
    request()->query->set('preview', '1');

    app(InvoiceService::class)->getPdfData($this->invoice);

    expect($this->recorder->attachments)->toBe([])
        ->and($this->capabilities->asked)->toBe(0);
});

test('download, mail attachment and customer portal all carry the same xml', function () {
    $this->invoice->getGeneratedPDFOrStream('invoice');

    $data = app(InvoiceService::class)->sendInvoiceData($this->invoice, [
        'subject' => 'Invoice',
        'body' => 'Please find the invoice attached.',
    ]);
    expect($data['attach']['data'])->toBeInstanceOf(ResponseStream::class);

    $emailLog = new EmailLog([
        'from' => 'billing@acme.test',
        'to' => 'buchhaltung@muster.test',
        'subject' => 'Invoice',
        'body' => 'Please find the invoice attached.',
        'token' => 'module-attachment-delivery-token',
    ]);
    $emailLog->mailable()->associate($this->invoice);
    $emailLog->save();

    get('/customer/invoices/view/module-attachment-delivery-token?pdf=1')->assertOk();

    expect($this->recorder->attachments)->toHaveCount(3)
        ->and(array_unique(array_map(fn (?FacturXAttachment $attachment): ?string => $attachment?->xml, $this->recorder->attachments)))
        ->toBe(["<rsm:CrossIndustryInvoice id=\"{$this->invoice->id}\"/>"]);
});

/**
 * An archive filed before e-invoicing was switched on lacks the XML, so it is
 * not served once a provider applies; and re-archiving replaces the old file
 * instead of leaving it behind (the archive used to clear a collection named
 * after the document id).
 */
test('an archive is served only while it matches whether attachments apply', function () {
    Setting::setSetting('save_pdf_to_disk', 'YES');
    FakeAttachmentProvider::$applies = false;

    expect($this->invoice->generatePDF('invoice', 'INV-000042'))->toBeTrue()
        ->and($this->invoice->getGeneratedPDF('invoice'))->not->toBeFalse();

    FakeAttachmentProvider::$applies = true;

    expect($this->invoice->getGeneratedPDF('invoice'))->toBeFalse();

    expect($this->invoice->generatePDF('invoice', 'INV-000042', true))->toBeTrue()
        ->and($this->invoice->fresh()->getMedia('invoice'))->toHaveCount(1)
        ->and($this->invoice->fresh()->getGeneratedPDF('invoice'))->not->toBeFalse();
});
