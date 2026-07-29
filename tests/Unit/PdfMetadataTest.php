<?php

use App\Models\Company;
use App\Support\Pdf\DompdfDriver;
use App\Support\Pdf\GotenbergPdfDriver;
use App\Support\Pdf\PdfMetadata;

/**
 * Generated files carried no document properties at all, so an archive of them
 * showed a column of blank titles. It matters more with PDF/A, whose point is
 * being readable later by someone who has only the file.
 */
test('it builds properties from the document and company', function () {
    $company = new Company(['name' => 'ACME Corp']);

    expect(PdfMetadata::forDocument('Invoice', 'INV-000042', $company))->toBe([
        'Title' => 'Invoice INV-000042',
        'Subject' => 'Invoice',
        'Author' => 'ACME Corp',
        'Creator' => config('app.name', 'InvoiceShelf'),
    ]);
});

test('missing pieces are left out rather than written as empty strings', function () {
    $metadata = PdfMetadata::forDocument('Invoice', null, null);

    expect($metadata)->not->toHaveKey('Author')
        ->and($metadata['Title'])->toBe('Invoice');
});

test('the gotenberg request carries the metadata', function () {
    config(['pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000']);

    $body = (string) (new GotenbergPdfDriver)->buildRequest(
        'app.pdf.partials.fonts',
        ['Title' => 'Invoice INV-000042', 'Author' => 'ACME Corp']
    )->getBody();

    expect($body)->toContain('metadata')
        ->and($body)->toContain('Invoice INV-000042')
        ->and($body)->toContain('ACME Corp');
});

/**
 * dompdf reads Title from the <title> element during render(), after any
 * addInfo() call, so metadata set through the API alone is silently overwritten
 * by whatever the template happened to put there -- and the two drivers end up
 * disagreeing about what the file is called.
 */
test('dompdf writes the metadata title into the markup so it is not overwritten', function () {
    $method = new ReflectionMethod(DompdfDriver::class, 'withDocumentTitle');

    $result = $method->invoke(
        new DompdfDriver,
        '<html><head><title>whatever the template said</title></head><body></body></html>',
        'Invoice INV-000042'
    );

    expect($result)->toContain('<title>Invoice INV-000042</title>')
        ->and($result)->not->toContain('whatever the template said');
});

test('a document with no title element gets one', function () {
    $method = new ReflectionMethod(DompdfDriver::class, 'withDocumentTitle');

    $result = $method->invoke(new DompdfDriver, '<html><head></head><body></body></html>', 'Invoice 42');

    expect($result)->toContain('<title>Invoice 42</title>');
});

test('a title is escaped rather than injected as markup', function () {
    $method = new ReflectionMethod(DompdfDriver::class, 'withDocumentTitle');

    $result = $method->invoke(
        new DompdfDriver,
        '<html><head><title>x</title></head><body></body></html>',
        'Invoice <script>alert(1)</script>'
    );

    expect($result)->not->toContain('<script>')
        ->and($result)->toContain('&lt;script&gt;');
});

test('no metadata leaves the markup alone', function () {
    $method = new ReflectionMethod(DompdfDriver::class, 'withDocumentTitle');
    $html = '<html><head><title>untouched</title></head><body></body></html>';

    expect($method->invoke(new DompdfDriver, $html, null))->toBe($html);
});
