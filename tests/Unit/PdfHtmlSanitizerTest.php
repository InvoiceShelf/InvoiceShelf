<?php

use App\Support\PdfHtmlSanitizer;

it('removes img tags that could trigger SSRF during PDF rendering', function () {
    $html = "<p>Hi</p><img src='http://example.com/x'>";

    expect(PdfHtmlSanitizer::sanitize($html))->not->toContain('<img');
});

it('preserves basic formatting tags', function () {
    $html = '<p><b>Bold</b> and <i>italic</i></p>';

    expect(PdfHtmlSanitizer::sanitize($html))->toContain('<b>')->toContain('<i>');
});

it('strips style and link attributes that may carry URLs', function () {
    $html = '<p style="background:url(http://example.com/)">x</p><a href="http://evil">y</a>';

    $out = PdfHtmlSanitizer::sanitize($html);

    expect($out)->not->toContain('style=')->not->toContain('href=')->not->toContain('example.com');
});

it('returns empty string for empty input', function () {
    expect(PdfHtmlSanitizer::sanitize(''))->toBe('');
});

it('normalizes legacy closing-br markup so lines are not collapsed in PDF output', function () {
    $html = 'line1</br>line2';

    $out = PdfHtmlSanitizer::sanitize($html);

    expect($out)->toContain('<br')->toContain('line1')->toContain('line2');
    expect($out)->not->toBe('line1line2');
});
