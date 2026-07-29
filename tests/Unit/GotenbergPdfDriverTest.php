<?php

use App\Support\Pdf\GotenbergPdfDriver;

/**
 * These assert against buildRequest(), which assembles the Chromium multipart
 * body without sending it. Nothing here needs a running Gotenberg.
 */
beforeEach(function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000',
        'pdf.connections.gotenberg.papersize' => '210mm 297mm',
    ]);
});

// A real view that renders without any shared document data, so these stay
// unit tests rather than needing a seeded invoice.
function gotenbergRequestBody(string $template = 'app.pdf.partials.fonts'): string
{
    return (string) (new GotenbergPdfDriver)->buildRequest($template)->getBody();
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
    config(['pdf.connections.gotenberg.papersize' => '8.5in 11in']);

    expect(gotenbergRequestBody())
        ->toContain('paperWidth')
        ->toContain('8.5in')
        ->toContain('11in');
});

test('the rendered document is sent as the index file', function () {
    expect(gotenbergRequestBody())->toContain('index.html');
});

test('it throws when the papersize config has an unexpected format', function () {
    config(['pdf.connections.gotenberg.papersize' => 'invalid']);

    expect(fn () => gotenbergRequestBody())
        ->toThrow(InvalidArgumentException::class, 'Invalid Gotenberg Papersize specified');
});

test('it throws when the configured host targets a private network address', function () {
    config(['pdf.connections.gotenberg.host' => 'http://10.0.0.1:3000']);

    expect(fn () => gotenbergRequestBody())
        ->toThrow(InvalidArgumentException::class, 'Invalid Gotenberg host');
});
