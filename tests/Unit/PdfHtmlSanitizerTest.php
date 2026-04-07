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

it('strips SSRF vectors injected via address-template placeholders', function () {
    // Simulates the output of GeneratesPdfTrait::getFormattedString() after a
    // malicious customer name like "Acme <img src='http://attacker/probe'>" has
    // been substituted into an address template via {BILLING_ADDRESS_NAME}.
    $html = "Acme <img src='http://attacker.test/probe'><br />123 Main St<br />Springfield";

    $out = PdfHtmlSanitizer::sanitize($html);

    expect($out)->not->toContain('<img');
    expect($out)->not->toContain('src=');
    expect($out)->not->toContain('attacker.test');
    expect($out)->toContain('Acme');
    expect($out)->toContain('123 Main St');
    expect($out)->toContain('Springfield');
});

it('strips iframe and link tags that could trigger SSRF', function () {
    $html = '<iframe src="http://attacker/x"></iframe><link rel="stylesheet" href="http://attacker/y.css">Hello';

    $out = PdfHtmlSanitizer::sanitize($html);

    expect($out)->not->toContain('<iframe');
    expect($out)->not->toContain('<link');
    expect($out)->not->toContain('attacker');
    expect($out)->toContain('Hello');
});

it('strips on* event handler attributes from allowed tags', function () {
    $html = '<p onload="alert(1)" onclick="x">click me</p>';

    $out = PdfHtmlSanitizer::sanitize($html);

    expect($out)->not->toContain('onload')->not->toContain('onclick')->not->toContain('alert');
    expect($out)->toContain('click me');
});
