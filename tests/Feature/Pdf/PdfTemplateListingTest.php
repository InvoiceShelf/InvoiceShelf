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
