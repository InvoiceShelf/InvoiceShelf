<?php

use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * getFormattedTemplates() lists every .blade.php it finds, which is how the
 * picker is populated. Companion header/footer views live next to their template
 * and are rendered with it, not chosen instead of it, so listing them would put
 * previewless entries in the dialog.
 */
beforeEach(function () {
    Storage::fake('pdf_templates');

    $this->customDir = Storage::disk('pdf_templates')->path('invoice');
    File::ensureDirectoryExists($this->customDir);
});

function writeCustomTemplate(string $dir, string $name): void
{
    File::put("{$dir}/{$name}.blade.php", '<html></html>');
}

test('companion views are kept out of the template picker', function () {
    writeCustomTemplate($this->customDir, 'branded');
    writeCustomTemplate($this->customDir, 'branded_header');
    writeCustomTemplate($this->customDir, 'branded_footer');

    $names = array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name');

    expect($names)->toContain('branded')
        ->and($names)->not->toContain('branded_header')
        ->and($names)->not->toContain('branded_footer');
});

test('the stock templates are still listed', function () {
    $names = array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name');

    expect($names)->toContain('invoice1', 'invoice2', 'invoice3');
});

/**
 * The exclusion matches on the suffix, so a template whose name merely mentions
 * a header is unaffected.
 */
test('a template whose name only contains the word is still listed', function () {
    writeCustomTemplate($this->customDir, 'header_led_design');

    $names = array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name');

    expect($names)->toContain('header_led_design');
});

/**
 * A custom template sharing a built-in's name used to appear twice with the same
 * label. findFormattedTemplate() array_reverses and takes the first match, so
 * the custom one silently won -- the picker gave no indication which tile you
 * were clicking.
 */
test('a custom template shadowing a built-in appears once, as the custom one', function () {
    writeCustomTemplate($this->customDir, 'invoice1');

    $templates = PdfTemplateUtils::getFormattedTemplates('invoice', '');
    $matching = array_values(array_filter($templates, fn ($t) => $t['name'] === 'invoice1'));

    expect($matching)->toHaveCount(1)
        ->and($matching[0]['custom'])->toBeTrue();
});

/**
 * A custom template needs a same-named .png. Without one the picker rendered
 * <img src="">: a blank tile, no error, no hint anything was missing.
 */
test('a custom template with no preview falls back rather than rendering blank', function () {
    writeCustomTemplate($this->customDir, 'no_preview');

    $templates = PdfTemplateUtils::getFormattedTemplates('invoice');
    $entry = collect($templates)->firstWhere('name', 'no_preview');

    expect($entry['path'])->toStartWith('data:image/png;base64,');
});

test('a custom template with its own preview uses it', function () {
    writeCustomTemplate($this->customDir, 'with_preview');
    File::put(
        "{$this->customDir}/with_preview.png",
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
    );

    $stock = collect(PdfTemplateUtils::getFormattedTemplates('invoice'))->firstWhere('name', 'invoice1');
    $custom = collect(PdfTemplateUtils::getFormattedTemplates('invoice'))->firstWhere('name', 'with_preview');

    expect($custom['path'])->toStartWith('data:image/png;base64,')
        ->and($custom['path'])->not->toBe($stock['path']);
});
