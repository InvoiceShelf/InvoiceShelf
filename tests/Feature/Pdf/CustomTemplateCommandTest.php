<?php

use App\Support\Pdf\PdfTemplateUtils;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command;

/**
 * make:template is the documented way to create a custom template, and had no
 * test at all.
 */
beforeEach(function () {
    Storage::fake('pdf_templates');
});

function customTemplatePath(string $type, string $file): string
{
    return Storage::disk('pdf_templates')->path("{$type}/{$file}");
}

test('it clones a template that renders through the custom namespace', function () {
    Artisan::call('make:template', ['name' => 'branded', '--type' => 'invoice']);

    expect(File::exists(customTemplatePath('invoice', 'branded.blade.php')))->toBeTrue();

    $markup = File::get(customTemplatePath('invoice', 'branded.blade.php'));

    expect($markup)->not->toContain('app.pdf.invoice')
        ->and($markup)->toContain('pdf_templates::invoice');
});

/**
 * Every custom template of a type used to include the same
 * partials/table.blade.php, written once and then reused, so editing the table
 * for one silently changed it for all of them.
 */
test('each template gets its own copy of the shared partial', function () {
    Artisan::call('make:template', ['name' => 'first', '--type' => 'invoice']);
    Artisan::call('make:template', ['name' => 'second', '--type' => 'invoice']);

    expect(File::exists(customTemplatePath('invoice', 'partials/first/table.blade.php')))->toBeTrue()
        ->and(File::exists(customTemplatePath('invoice', 'partials/second/table.blade.php')))->toBeTrue();

    expect(File::get(customTemplatePath('invoice', 'first.blade.php')))
        ->toContain('pdf_templates::invoice.partials.first.table');
});

test('a preview image is written so the picker has something to show', function () {
    Artisan::call('make:template', ['name' => 'branded', '--type' => 'invoice']);

    expect(File::exists(customTemplatePath('invoice', 'branded.png')))->toBeTrue();
});

test('the new template shows up in the picker', function () {
    Artisan::call('make:template', ['name' => 'branded', '--type' => 'invoice']);

    $names = array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name');

    expect($names)->toContain('branded');
});

/**
 * --type was never checked against the supported list. An unsupported value
 * skipped the interactive prompt and then died on an uncaught
 * FileNotFoundException looking for e.g. payment1.blade.php.
 */
test('an unsupported type is refused with a message rather than a stack trace', function () {
    $exit = Artisan::call('make:template', ['name' => 'receipt', '--type' => 'payment']);

    expect($exit)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unsupported template type');
});

test('a name that would escape the templates directory is refused', function (string $name) {
    $exit = Artisan::call('make:template', ['name' => $name, '--type' => 'invoice']);

    expect($exit)->toBe(Command::INVALID);
})->with([
    '../escaped',
    'nested/path',
]);

test('an existing name is not overwritten', function () {
    Artisan::call('make:template', ['name' => 'branded', '--type' => 'invoice']);
    File::put(customTemplatePath('invoice', 'branded.blade.php'), 'EDITED BY USER');

    $exit = Artisan::call('make:template', ['name' => 'branded', '--type' => 'invoice']);

    expect($exit)->toBe(Command::INVALID)
        ->and(File::get(customTemplatePath('invoice', 'branded.blade.php')))->toBe('EDITED BY USER');
});
