<?php

/**
 * dompdf does not use a declared `line-height` directly. It scales it by the
 * font's own height:
 *
 *     rendered = declared × (ascent + descent) / unitsPerEm × font_height_ratio
 *
 * The bundled Noto Sans reports 1.362 for that middle term, so at dompdf's stock
 * font_height_ratio of 1.1 every line-height in every document came out 1.4985×
 * what the CSS asked for. Chromium honours the declared value exactly, and that
 * single factor was the whole vertical disagreement between the two drivers --
 * measured at ~100pt across a page before this, and under 10pt on most templates
 * after.
 *
 * config/dompdf.php sets the ratio to 1/1.362 to cancel the font term. The
 * invariant that buys is simply: the font's reported height equals the font
 * size, so a declared length is rendered at that length.
 *
 * This is calibrated against Noto Sans. Swap the default face, or take a dompdf
 * upgrade that changes the computation, and this fails rather than quietly
 * reintroducing the drift.
 */
function notoSansHeightAt(float $size): float
{
    // dompdf registers a face the first time a document declares it. Render one
    // that does, or this depends on another test in the same worker having
    // rendered a PDF first, which a fresh checkout does not guarantee.
    $pdf = app('dompdf.wrapper');
    $pdf->loadView('app.pdf.partials.fonts')->output();

    $metrics = $pdf->getDomPDF()->getFontMetrics();
    $font = $metrics->getFont('NotoSans', 'normal');

    expect($font)->not->toBeFalsy('NotoSans should resolve; it is the bundled default face');

    return $metrics->getFontHeight($font, $size);
}

test('a declared line-height is rendered at its declared size', function (float $size) {
    expect(notoSansHeightAt($size))->toEqualWithDelta($size, 0.01);
})->with([9.0, 12.0, 15.0, 18.0, 24.0]);

test('the configured ratio is the one that cancels the font metrics', function () {
    // Guards the value itself, so a well-meaning "restore the dompdf default"
    // shows up as a failing test rather than as documents silently growing.
    expect(config('dompdf.defines.font_height_ratio'))->toEqualWithDelta(0.7342, 0.0005);
});

/**
 * The stock ratio is what the drift looked like: 1.1 × 1.362 ≈ 1.4985, which is
 * within a rounding error of the 1.5 that a hand-tuned compensation shim had
 * arrived at empirically.
 */
test('dompdf stock ratio would inflate every line by about half again', function () {
    $natural = notoSansHeightAt(12.0) / config('dompdf.defines.font_height_ratio');

    expect($natural * 1.1 / 12.0)->toEqualWithDelta(1.4985, 0.005);
});
