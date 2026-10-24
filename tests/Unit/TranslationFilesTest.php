<?php

use Illuminate\Support\Facades\File;

/**
 * The locale files come from Crowdin. .github/scripts/clean-translations.php
 * keeps them to what the app can show: no keys en.json lost, no translation
 * whose {placeholders} differ from English, no empty strings. The Crowdin
 * workflow runs it on every sync; this keeps the committed files clean.
 */
function cleanTranslations(string $directory, bool $check = false): array
{
    exec(implode(' ', array_map('escapeshellarg', array_filter([
        PHP_BINARY,
        base_path('.github/scripts/clean-translations.php'),
        $check ? '--check' : null,
        $directory,
    ]))).' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/invoiceshelf-lang-'.uniqid();
    File::makeDirectory($this->directory);
    File::put($this->directory.'/en.json', json_encode([
        'general' => ['save' => 'Save', 'greeting' => 'Hello {name}', 'invoices' => 'Invoice | Invoices'],
    ]));
});

afterEach(fn () => File::deleteDirectory($this->directory));

test('every committed locale file is clean', function () {
    [$code, $output] = cleanTranslations(lang_path(), check: true);

    expect($code)->toBe(0, $output);
});

test('keys English no longer has, broken placeholders and empty strings are dropped', function () {
    File::put($this->directory.'/sl.json', json_encode([
        'general' => ['save' => 'Shrani', 'greeting' => 'Pozdravljeni {ime}', 'invoices' => ''],
        'users' => ['title' => 'Uporabniki'],
    ]));

    [$code] = cleanTranslations($this->directory);

    expect($code)->toBe(0)
        ->and(json_decode(File::get($this->directory.'/sl.json'), true))->toBe(['general' => ['save' => 'Shrani']]);
});

test('placeholders may move, and plural forms may differ from English', function () {
    File::put($this->directory.'/de.json', json_encode([
        'general' => ['greeting' => '{name}, hallo', 'invoices' => 'Rechnungen'],
    ]));

    cleanTranslations($this->directory);

    expect(json_decode(File::get($this->directory.'/de.json'), true))
        ->toBe(['general' => ['greeting' => '{name}, hallo', 'invoices' => 'Rechnungen']]);
});

test('files are written as Crowdin exports them, and a clean file is left alone', function () {
    File::put($this->directory.'/de.json', json_encode(['general' => ['save' => 'Speichern']]));

    cleanTranslations($this->directory);

    expect(File::get($this->directory.'/de.json'))->toBe("{\n  \"general\": {\n    \"save\": \"Speichern\"\n  }\n}\n")
        ->and(cleanTranslations($this->directory, check: true)[0])->toBe(0);
});

test('a file that is not a JSON object stops the run', function () {
    File::put($this->directory.'/fr.json', '{"general": ');

    [$code, $output] = cleanTranslations($this->directory);

    expect($code)->toBe(2)
        ->and($output)->toContain('fr.json is not a JSON object');
});
