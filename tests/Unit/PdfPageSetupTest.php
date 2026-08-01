<?php

use App\Support\Pdf\PdfPageSetup;

/**
 * Page geometry is stored once and translated per driver. Gotenberg takes CSS
 * lengths; dompdf takes points, and margins only through an @page rule.
 *
 * Margins default to nothing: the stock templates carry their own insets, and
 * invoice2/estimate2 are built around a header band that only reaches the paper
 * edge at zero. A bare 0 is valid CSS and needs no unit.
 */
test('it falls back to A4 with no page margin', function () {
    config(['pdf.page' => []]);

    $page = PdfPageSetup::fromConfig();

    expect($page->width)->toBe('210mm')
        ->and($page->height)->toBe('297mm')
        ->and($page->orientation)->toBe('portrait')
        ->and($page->marginCss())->toBe('0 0 0 0');
});

test('a bare zero is accepted and converts to no offset', function () {
    expect(PdfPageSetup::toPoints('0'))->toBe(0.0);

    config(['pdf.page.margin_top' => '0']);

    expect(PdfPageSetup::fromConfig()->marginTop)->toBe('0');
});

test('it converts every accepted unit to points', function (string $length, float $points) {
    expect(PdfPageSetup::toPoints($length))->toEqualWithDelta($points, 0.01);
})->with([
    ['72pt', 72.0],
    ['1in', 72.0],
    ['25.4mm', 72.0],
    ['2.54cm', 72.0],
    ['96px', 72.0],   // CSS px is 1/96in
    ['6pc', 72.0],
    ['210mm', 595.28], // A4 width, matching dompdf's own table
]);

test('it rejects a length with no unit', function () {
    expect(fn () => PdfPageSetup::toPoints('210'))
        ->toThrow(InvalidArgumentException::class, 'Invalid PDF page length');
});

test('dompdf gets a points array and is left to swap for landscape itself', function () {
    config(['pdf.page.paper_width' => '210mm', 'pdf.page.paper_height' => '297mm']);
    config(['pdf.page.orientation' => 'landscape']);

    $page = PdfPageSetup::fromConfig();

    // Dompdf::getPaperSize() applies the swap when the orientation argument says
    // landscape, so handing it pre-swapped dimensions would cancel out.
    expect($page->dompdfPaper())->toEqualWithDelta([0.0, 0.0, 595.28, 841.89], 0.01)
        ->and($page->isLandscape())->toBeTrue();
});

/**
 * Gotenberg's margins() argument order is top, bottom, left, right -- not the
 * CSS order. Getting it wrong silently swaps the side margins.
 */
test('gotenberg margins are ordered top, bottom, left, right', function () {
    config([
        'pdf.page.margin_top' => '1mm',
        'pdf.page.margin_right' => '2mm',
        'pdf.page.margin_bottom' => '3mm',
        'pdf.page.margin_left' => '4mm',
    ]);

    expect(PdfPageSetup::fromConfig()->gotenbergMargins())->toBe(['1mm', '3mm', '4mm', '2mm']);
});

test('css margins are ordered top, right, bottom, left', function () {
    config([
        'pdf.page.margin_top' => '1mm',
        'pdf.page.margin_right' => '2mm',
        'pdf.page.margin_bottom' => '3mm',
        'pdf.page.margin_left' => '4mm',
    ]);

    expect(PdfPageSetup::fromConfig()->marginCss())->toBe('1mm 2mm 3mm 4mm');
});

test('a blank stored value falls back rather than producing an invalid length', function () {
    config(['pdf.page.paper_width' => '   ', 'pdf.page.margin_top' => '']);

    $page = PdfPageSetup::fromConfig();

    expect($page->width)->toBe('210mm')
        ->and($page->marginTop)->toBe('0');
});

/**
 * Blank means "not configured" and falls back, but a value that is set and
 * wrong is an operator mistake worth surfacing. Without this the two drivers
 * would diverge on it: dompdf throws while converting to points, while Gotenberg
 * would forward the string and quietly render at some other size.
 */
test('a malformed length is rejected rather than silently ignored', function (string $key) {
    config([$key => '210']);

    expect(fn () => PdfPageSetup::fromConfig())
        ->toThrow(InvalidArgumentException::class, $key);
})->with([
    'pdf.page.paper_width',
    'pdf.page.paper_height',
    'pdf.page.margin_top',
    'pdf.page.margin_left',
]);

test('an unrecognised orientation is treated as portrait', function () {
    config(['pdf.page.orientation' => 'sideways']);

    expect(PdfPageSetup::fromConfig()->isLandscape())->toBeFalse();
});
