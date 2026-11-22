<?php

use App\Platform\Pdf\Rendering\FacturXAttachment;
use App\Platform\Pdf\Rendering\GotenbergPdfDriver;

/**
 * These assert against buildRequest(), which assembles the Chromium multipart
 * body without sending it. Nothing here needs a running Gotenberg.
 */
beforeEach(function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000',
        'pdf.page.paper_width' => '210mm',
        'pdf.page.paper_height' => '297mm',
    ]);
});

// A real view that renders without any shared document data, so these stay
// unit tests rather than needing a seeded invoice.
function gotenbergRequestBody(
    string $template = 'app.pdf.partials.fonts',
    ?FacturXAttachment $attachment = null,
): string {
    return (string) (new GotenbergPdfDriver)
        ->buildRequest($template, [], null, $attachment)
        ->getBody();
}

/**
 * Stand-in for what an e-invoicing module hands over. Only the transport
 * matters here, so the payload is kept short enough to be recognised in the
 * multipart body.
 */
function facturXTestAttachment(): FacturXAttachment
{
    return new FacturXAttachment(
        '<?xml version="1.0" encoding="UTF-8"?><rsm:CrossIndustryInvoice '
        .'xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>'
    );
}

/**
 * printBackground governs the root (body/html) background only — Chromium paints
 * element backgrounds regardless, checked against gotenberg:8. dompdf paints the
 * body background, so setting this keeps a custom template that styles `body`
 * looking the same on either driver. No stock template sets one.
 */
test('the chromium request asks for the page background to be printed', function () {
    expect(gotenbergRequestBody())->toContain('printBackground');
});

/**
 * config/dompdf.php renders as `screen`; Chromium's own default is `print`. The
 * two drivers should not disagree about which media type a template is styled for.
 */
test('the chromium request emulates the same media type dompdf uses', function () {
    expect(gotenbergRequestBody())->toContain('emulatedMediaType');
});

test('the configured paper size reaches the request', function () {
    config(['pdf.page.paper_width' => '8.5in', 'pdf.page.paper_height' => '11in']);

    expect(gotenbergRequestBody())
        ->toContain('paperWidth')
        ->toContain('8.5in')
        ->toContain('11in');
});

test('the rendered document is sent as the index file', function () {
    expect(gotenbergRequestBody())->toContain('index.html');
});

test('it throws when a page length has an unexpected format', function () {
    config(['pdf.page.paper_width' => 'invalid']);

    expect(fn () => gotenbergRequestBody())
        ->toThrow(InvalidArgumentException::class, 'Invalid PDF page length');
});

test('it throws when the configured host targets a private network address', function () {
    config(['pdf.connections.gotenberg.host' => 'http://10.0.0.1:3000']);

    expect(fn () => gotenbergRequestBody())
        ->toThrow(InvalidArgumentException::class, 'Invalid Gotenberg host');
});

/**
 * A hybrid e-invoice: the attachment has to reach Gotenberg as the
 * `facturxXml` file named factur-x.xml, declared as Profile EN 16931, inside a
 * PDF/A-3b container. Anything less and the produced PDF is not a ZUGFeRD
 * e-invoice, however valid the XML itself is.
 */
test('an e-invoice attachment travels with the request as the factur-x xml file', function () {
    $body = gotenbergRequestBody('app.pdf.partials.fonts', facturXTestAttachment());

    expect($body)
        ->toContain('name="facturxXml"')
        ->toContain('filename="factur-x.xml"')
        ->toContain('CrossIndustryInvoice')
        ->toContain('name="facturxConformanceLevel"')
        ->toContain('EN 16931')
        ->toContain('name="facturxDocumentType"')
        ->toContain('INVOICE');
});

test('an embedded e-invoice is built into a PDF/A-3b container', function () {
    config(['pdf.connections.gotenberg.pdfa' => null]);

    $body = gotenbergRequestBody('app.pdf.partials.fonts', facturXTestAttachment());

    expect($body)->toContain('name="pdfa"')->toContain('PDF/A-3b');
});

/**
 * A file may only be embedded from PDF/A-3 onwards, so the instance-wide
 * setting cannot be allowed to decide the conformance of a hybrid PDF — and it
 * has to be replaced rather than added to, since the SDK appends form fields
 * and Gotenberg would receive two conflicting `pdfa` values.
 */
test('the e-invoice conformance replaces the configured pdf/a format', function () {
    config(['pdf.connections.gotenberg.pdfa' => 'PDF/A-1a']);

    $body = gotenbergRequestBody('app.pdf.partials.fonts', facturXTestAttachment());

    expect($body)->toContain('PDF/A-3b')
        ->and($body)->not->toContain('PDF/A-1a')
        ->and(substr_count($body, 'name="pdfa"'))->toBe(1);
});

test('the configured pdf/a format still applies to an ordinary pdf', function () {
    config(['pdf.connections.gotenberg.pdfa' => 'PDF/A-1a']);

    expect(gotenbergRequestBody())->toContain('name="pdfa"')->toContain('PDF/A-1a');
});

/**
 * Gotenberg writes document properties with exiftool, which stores a bare
 * Author key in XMP as pdf:Author — a property no PDF/A-recognised schema
 * defines, so veraPDF fails the whole container on ISO 19005-3 clause
 * 6.6.2.3.1 (caught by the CI conformance job). In an archival file the
 * authoring entity belongs in dc:creator, which is where exiftool maps the
 * bare Creator key.
 */
test('a hybrid pdf carries its author as the archival creator property', function () {
    $body = (string) (new GotenbergPdfDriver)
        ->buildRequest('app.pdf.partials.fonts', [
            'Title' => 'Invoice INV-1',
            'Author' => 'Acme Inc',
            'Creator' => 'InvoiceShelf',
        ], null, facturXTestAttachment())
        ->getBody();

    expect($body)
        ->toContain('"Creator":"Acme Inc"')
        ->not->toContain('Author');
});

test('the configured pdf/a format sanitises the author the same way', function () {
    config(['pdf.connections.gotenberg.pdfa' => 'PDF/A-3b']);

    $body = (string) (new GotenbergPdfDriver)
        ->buildRequest('app.pdf.partials.fonts', ['Author' => 'Acme Inc'])
        ->getBody();

    expect($body)
        ->toContain('"Creator":"Acme Inc"')
        ->not->toContain('Author');
});

test('an ordinary pdf keeps its author property', function () {
    config(['pdf.connections.gotenberg.pdfa' => null]);

    $body = (string) (new GotenbergPdfDriver)
        ->buildRequest('app.pdf.partials.fonts', ['Author' => 'Acme Inc'])
        ->getBody();

    expect($body)->toContain('"Author":"Acme Inc"');
});

/**
 * No attachment means the request is exactly the one an ordinary PDF has
 * always produced.
 */
test('a request without an e-invoice carries no factur-x fields at all', function () {
    expect(gotenbergRequestBody())->not->toContain('facturx');
});

test('an attachment cannot be built from empty xml or an unknown profile', function () {
    expect(fn () => new FacturXAttachment('   '))
        ->toThrow(InvalidArgumentException::class, 'cannot carry empty XML')
        ->and(fn () => new FacturXAttachment('<x/>', 'EN16931'))
        ->toThrow(InvalidArgumentException::class, 'Unknown Factur-X profile');
});

test('an xrechnung profile is declared as such', function () {
    $body = gotenbergRequestBody('app.pdf.partials.fonts', new FacturXAttachment('<x/>', 'XRECHNUNG'));

    expect($body)->toContain('XRECHNUNG');
});
