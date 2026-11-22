<?php

use InvoiceShelf\Modules\Contracts\Host\QrCodeGenerator;
use InvoiceShelf\Modules\Qr\ErrorCorrection;

/**
 * Module API 1.6.0: modules draw QR codes through the host instead of
 * bundling a QR library of their own.
 */
test('a module gets an SVG of the size it asked for', function () {
    $svg = app(QrCodeGenerator::class)->svg('https://pay.example.com/INV-1', 240);

    expect($svg)->toStartWith('<?xml')
        ->toContain('<svg')
        ->toContain('width="240"')
        ->toContain('height="240"');
});

test('the data URI carries the same SVG', function () {
    $generator = app(QrCodeGenerator::class);

    $uri = $generator->svgDataUri('BCD', 120, ErrorCorrection::High, 1);

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($uri, strlen('data:image/svg+xml;base64,'))))
        ->toBe($generator->svg('BCD', 120, ErrorCorrection::High, 1));
});

test('a higher error correction level draws a denser code', function () {
    $generator = app(QrCodeGenerator::class);
    $content = str_repeat('https://pay.example.com/', 4);

    expect($generator->svg($content, 200, ErrorCorrection::High))
        ->not->toBe($generator->svg($content, 200, ErrorCorrection::Low));
});

test('text beyond ASCII is drawn, not refused', function () {
    $payload = "BCD\n002\n1\nSCT\n\nMüller & Söhne GmbH\nDE89370400440532013000\nEUR12.50";

    expect(app(QrCodeGenerator::class)->svg($payload))->toContain('<svg');
});

test('empty or oversized content is refused with an invalid argument', function (string $content) {
    app(QrCodeGenerator::class)->svg($content, 200, ErrorCorrection::High);
})->with([
    'empty' => [''],
    'too long for a QR code' => [str_repeat('x', 4000)],
])->throws(InvalidArgumentException::class);
