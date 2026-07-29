<?php

use App\Services\FontService;
use App\Support\Pdf\GotenbergPdfDriver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * FontService writes absolute host paths into the @font-face rules. dompdf shares
 * that filesystem so they resolve; Chromium runs inside the Gotenberg container
 * and cannot see any of it, so every installed package silently failed to load
 * and documents fell back to whatever fonts that image ships. The docs recommend
 * Gotenberg specifically for mixed-script documents, which made this the wrong
 * way round.
 */
beforeEach(function () {
    config(['pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000']);

    $this->views = sys_get_temp_dir().'/is-fonts-'.uniqid();
    File::ensureDirectoryExists($this->views);
    View::addNamespace('fonttest', $this->views);

    $this->fonts = app(FontService::class)->getInstalledFontFilePaths();
});

afterEach(function () {
    File::deleteDirectory($this->views);
});

/**
 * Distinct view names per case: Blade caches compiled views by path, so reusing
 * one filename can silently render the previous case's markup.
 */
function fontRequestBody(string $markup, string $dir, string $name = 'doc'): string
{
    File::put("{$dir}/{$name}.blade.php", $markup);
    View::getFinder()->flush();

    return (string) (new GotenbergPdfDriver)->buildRequest("fonttest::{$name}")->getBody();
}

test('a document using the font partial gets the files sent with it', function () {
    expect($this->fonts)->not->toBeEmpty('no bundled fonts installed to test against');

    $body = fontRequestBody(
        '<html><head>@include("app.pdf.partials.fonts")</head><body>x</body></html>',
        $this->views,
        'with-fonts'
    );

    $filename = array_key_first($this->fonts);

    expect($body)->toContain($filename);
});

/**
 * Gotenberg unpacks assets next to index.html, so the rule has to name the file
 * rather than a path this container happens to have.
 */
test('the absolute host path is rewritten out of the markup', function () {
    $body = fontRequestBody(
        '<html><head>@include("app.pdf.partials.fonts")</head><body>x</body></html>',
        $this->views,
        'rewritten'
    );

    foreach ($this->fonts as $path) {
        expect($body)->not->toContain($path);
    }
});

/**
 * A CJK package is several megabytes, so it should not ride along on a request
 * whose document never mentions it.
 */
test('fonts the document does not reference are not sent', function () {
    $body = fontRequestBody('<html><body>no font rules here</body></html>', $this->views, 'plain');

    foreach ($this->fonts as $filename => $path) {
        expect($body)->not->toContain($filename);
    }
});

test('the font files themselves are attached, not just referenced', function () {
    $withFonts = fontRequestBody(
        '<html><head>@include("app.pdf.partials.fonts")</head><body>x</body></html>',
        $this->views,
        'payload-with'
    );
    $without = fontRequestBody('<html><body>x</body></html>', $this->views, 'payload-without');

    // The difference is the font payload, which is far larger than the markup.
    expect(strlen($withFonts))->toBeGreaterThan(strlen($without) + 100_000);
});
