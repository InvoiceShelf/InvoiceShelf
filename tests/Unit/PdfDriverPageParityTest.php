<?php

use App\Support\Pdf\DompdfDriver;
use App\Support\Pdf\GotenbergPdfDriver;
use App\Support\Pdf\PdfPageSetup;

/**
 * The page setup is only worth anything if both drivers land on the same page.
 *
 * dompdf gets a points array plus an orientation argument; Gotenberg gets CSS
 * lengths plus landscape(), and its margins() takes a different argument order
 * than CSS. Those are three separate chances to get it subtly wrong, and the
 * failure mode is a slightly shifted document rather than an error.
 *
 * The dompdf side is checked against real rendered output. Gotenberg's is
 * checked on the request it would send, so this needs no running service --
 * the rendered comparison against a live gotenberg:8 is in the PR description.
 */
beforeEach(function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000',
        'pdf.page.paper_width' => '210mm',
        'pdf.page.paper_height' => '297mm',
        'pdf.page.orientation' => 'portrait',
        'pdf.page.margin_top' => '1.2cm',
        'pdf.page.margin_right' => '1.2cm',
        'pdf.page.margin_bottom' => '1.2cm',
        'pdf.page.margin_left' => '1.2cm',
    ]);
});

test('the gotenberg request carries the configured page geometry', function () {
    $body = (string) (new GotenbergPdfDriver)->buildRequest('app.pdf.partials.fonts')->getBody();

    expect($body)->toContain('210mm')
        ->and($body)->toContain('297mm')
        ->and($body)->toContain('marginTop')
        ->and($body)->toContain('1.2cm');
});

test('landscape is requested of gotenberg rather than pre-swapping the paper', function () {
    config(['pdf.page.orientation' => 'landscape']);

    $body = (string) (new GotenbergPdfDriver)->buildRequest('app.pdf.partials.fonts')->getBody();

    // Still the portrait pair: landscape() does the swap on Gotenberg's side,
    // exactly as setPaper()'s orientation argument does on dompdf's.
    expect($body)->toContain('landscape')
        ->and($body)->toContain('210mm')
        ->and($body)->toContain('297mm');
});

test('dompdf renders at the configured paper size', function () {
    $pdf = (new DompdfDriver)->loadView('app.pdf.partials.fonts');

    // A4 in points, per dompdf's own table.
    expect($pdf->output())->toStartWith('%PDF-');

    $page = PdfPageSetup::fromConfig();
    expect($page->dompdfPaper())->toEqualWithDelta([0.0, 0.0, 595.28, 841.89], 0.01);
});

/**
 * dompdf has no margin API, so the only way margins reach it is an injected
 * page rule. If that injection stops happening, output silently reverts to
 * dompdf's built-in 1.2cm and nothing else notices -- so assert on real
 * rendered bytes, not just on the string helper.
 */
test('changing the margins changes what dompdf actually renders', function () {
    config(['pdf.page.margin_top' => '0mm', 'pdf.page.margin_left' => '0mm']);
    $tight = (new DompdfDriver)->loadView('app.pdf.partials.fonts')->output();

    config(['pdf.page.margin_top' => '40mm', 'pdf.page.margin_left' => '40mm']);
    $roomy = (new DompdfDriver)->loadView('app.pdf.partials.fonts')->output();

    expect($tight)->toStartWith('%PDF-')
        ->and($roomy)->toStartWith('%PDF-')
        ->and($tight)->not->toBe($roomy);
});

test('the page size reaches dompdf rather than config/dompdf.php\'s fixed a4', function () {
    config(['pdf.page.paper_width' => '8.5in', 'pdf.page.paper_height' => '14in']);
    $legal = (new DompdfDriver)->loadView('app.pdf.partials.fonts')->output();

    config(['pdf.page.paper_width' => '210mm', 'pdf.page.paper_height' => '297mm']);
    $a4 = (new DompdfDriver)->loadView('app.pdf.partials.fonts')->output();

    expect($legal)->not->toBe($a4);
});

/**
 * Injected at the top of the head element so a template declaring its own page
 * rule still wins -- later rules of equal specificity take precedence in CSS.
 */
test('the injected rule sits at the start of head so a template can override it', function () {
    $html = '<html><head><style>@page { margin: 0; }</style></head><body></body></html>';

    $method = new ReflectionMethod(DompdfDriver::class, 'withPageMargins');
    $result = $method->invoke(new DompdfDriver, $html, PdfPageSetup::fromConfig());

    expect(strpos($result, '1.2cm'))->toBeLessThan(strpos($result, 'margin: 0'));
});

test('markup with no head still receives the rule', function () {
    $method = new ReflectionMethod(DompdfDriver::class, 'withPageMargins');
    $result = $method->invoke(new DompdfDriver, '<p>bare</p>', PdfPageSetup::fromConfig());

    expect($result)->toStartWith('<style>@page');
});
