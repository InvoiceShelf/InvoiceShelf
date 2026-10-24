<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\FacturXAttachment;
use App\Platform\Pdf\Rendering\PdfMetadata;
use Illuminate\Support\Facades\Artisan;
use Tests\Conformance\Support\ConformanceEnvironment;

/**
 * Renders an invoice with a real Gotenberg, embeds a canned EN 16931 XML, and
 * hands the result to Mustang CLI (XML against the EN 16931 XSD and
 * Schematron, the container, the Factur-X metadata) and veraPDF (PDF/A-3b).
 *
 * The XML is a fixture: building it is the e-invoicing module's job, and its
 * own suite validates what it builds. This proves the host's half, that the
 * PDF it renders around any valid XML is a conformant hybrid e-invoice.
 *
 * Needs a Gotenberg service and two Java tools, so it has its own PHPUnit
 * configuration that no ordinary run loads:
 *
 *     php artisan test --configuration=phpunit.conformance.xml
 *
 * @see .github/workflows/pdf-conformance.yaml
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $host = ConformanceEnvironment::gotenbergHost();

    // A Gotenberg service in CI answers on a loopback or container address,
    // which PrivateNetworkGuard blocks unless the operator names it, exactly
    // as a sidecar deployment does.
    config([
        'pdf.driver' => 'gotenberg',
        'pdf.connections.gotenberg.host' => $host,
        'network.allowed_private_hosts.gotenberg' => [$host],
        'pdf.connections.gotenberg.pdfa' => null,
    ]);

    $company = User::find(1)->companies()->first();

    $this->invoice = Invoice::factory()->hasItems(2)->create([
        'company_id' => $company->id,
        'template_name' => 'invoice1',
    ]);
});

/**
 * Render the invoice's own template through the PDF facade, as a download
 * would, and keep the file so the CI job can upload it.
 */
function renderConformanceInvoice(Invoice $invoice, string $name, ?FacturXAttachment $attachment): string
{
    // The HTML preview shares the template data without rendering a PDF.
    request()->query->set('preview', '1');
    app(InvoiceService::class)->getPdfData($invoice);
    request()->query->remove('preview');

    $pdf = Pdf::loadView('app.pdf.invoice.invoice1', PdfMetadata::forDocument(
        'Invoice',
        $invoice->invoice_number,
        $invoice->company,
    ), null, $attachment)->output();

    $path = ConformanceEnvironment::artifactDirectory().'/'.$name.'.pdf';
    file_put_contents($path, $pdf);

    return $path;
}

test('an invoice pdf with an embedded en 16931 xml is a conformant zugferd e-invoice', function () {
    $attachment = new FacturXAttachment((string) file_get_contents(__DIR__.'/fixtures/en16931-invoice.xml'));

    $path = renderConformanceInvoice($this->invoice, 'hybrid-invoice', $attachment);

    expect(file_get_contents($path))->toStartWith('%PDF');

    $mustang = ConformanceEnvironment::validateWithMustang($path);
    expect($mustang->isValid())->toBeTrue($mustang->summary());

    $veraPdf = ConformanceEnvironment::validateWithVeraPdf($path);
    expect($veraPdf->isCompliant())->toBeTrue($veraPdf->summary());
});

/**
 * Gotenberg writes the Author property as pdf:Author, which PDF/A forbids, so
 * every PDF/A the host rendered failed veraPDF until the driver moved it to
 * Creator. This is the configured-PDF/A path, with no e-invoice involved.
 */
test('an ordinary invoice pdf rendered as configured pdf/a-3b passes verapdf', function () {
    config(['pdf.connections.gotenberg.pdfa' => 'PDF/A-3b']);

    $path = renderConformanceInvoice($this->invoice, 'pdfa-invoice', null);

    $veraPdf = ConformanceEnvironment::validateWithVeraPdf($path);
    expect($veraPdf->isCompliant())->toBeTrue($veraPdf->summary());
});
