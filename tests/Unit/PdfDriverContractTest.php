<?php

use App\Platform\Pdf\Rendering\DompdfDriver;
use App\Platform\Pdf\Rendering\DompdfResponse;
use App\Platform\Pdf\Rendering\FacturXAttachment;
use App\Platform\Pdf\Rendering\GotenbergPdfDriver;
use App\Platform\Pdf\Rendering\GotenbergPdfResponse;
use App\Platform\Pdf\Rendering\PdfDriver;
use App\Platform\Pdf\Rendering\PdfDriverFactory;
use App\Platform\Pdf\Rendering\ResponseStream;

/**
 * PdfDriver and ResponseStream existed but nothing implemented them, so the
 * factory could hand back the raw dompdf wrapper for one driver and a bespoke
 * class for the other. That is how the report controllers came to call
 * ->download() on a Gotenberg response that had no such method: a fatal error on
 * every report download, invisible because no type ever asserted the two were
 * interchangeable. These tests are that assertion.
 */
test('the factory returns a PdfDriver for every supported driver', function (string $driver) {
    expect(PdfDriverFactory::create($driver))->toBeInstanceOf(PdfDriver::class);
})->with(['dompdf', 'gotenberg']);

test('the factory rejects an unknown driver', function () {
    expect(fn () => PdfDriverFactory::create('wkhtmltopdf'))
        ->toThrow(InvalidArgumentException::class, 'Invalid PdfDriver requested');
});

test('every driver implements the driver contract', function (string $class) {
    expect(is_subclass_of($class, PdfDriver::class))->toBeTrue();
})->with([DompdfDriver::class, GotenbergPdfDriver::class]);

/**
 * The report controllers call stream(), download() and output() with no
 * arguments, so each has to be callable bare on either driver's response.
 */
test('every response implements the full response contract', function (string $class) {
    expect(is_subclass_of($class, ResponseStream::class))->toBeTrue();

    foreach (['stream', 'download', 'output'] as $method) {
        expect(method_exists($class, $method))->toBeTrue();

        $required = (new ReflectionMethod($class, $method))->getNumberOfRequiredParameters();
        expect($required)->toBe(0, "{$class}::{$method}() must be callable without arguments");
    }
})->with([DompdfResponse::class, GotenbergPdfResponse::class]);

test('dompdf renders a real pdf through the contract', function () {
    $pdf = (new DompdfDriver)->loadView('app.pdf.partials.fonts');

    expect($pdf)->toBeInstanceOf(ResponseStream::class)
        ->and($pdf->output())->toStartWith('%PDF-');
});

/**
 * dompdf cannot build the PDF/A-3 container a hybrid e-invoice needs, so it
 * takes the attachment the contract allows and renders the ordinary PDF.
 */
test('dompdf accepts a factur-x attachment and renders the ordinary pdf', function () {
    $pdf = (new DompdfDriver)->loadView('app.pdf.partials.fonts', [], null, new FacturXAttachment('<x/>'));

    expect($pdf->output())->toStartWith('%PDF-')
        ->not->toContain('factur-x.xml');
});

test('dompdf offers the document as an attachment when downloaded', function () {
    $response = (new DompdfDriver)->loadView('app.pdf.partials.fonts')->download('expenses.pdf');

    expect($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->headers->get('content-disposition'))->toContain('expenses.pdf');
});
