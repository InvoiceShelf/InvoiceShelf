<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Exercises the custom generator stubs shipped from the invoiceshelf/modules
 * package. These override nwidart's defaults so that a fresh
 * `php artisan module:make` scaffold already includes the Registry boilerplate
 * and the starter i18n files that the boilerplate references.
 *
 * The test generates a throwaway module, inspects the generated files, then
 * cleans it up so the rest of the suite is unaffected.
 */
beforeEach(function () {
    $this->scaffoldModule = 'ScaffoldProbe';
    $this->scaffoldPath = base_path('Modules/'.$this->scaffoldModule);

    // Make sure no leftover from a previous crashed test.
    if (File::isDirectory($this->scaffoldPath)) {
        File::deleteDirectory($this->scaffoldPath);
    }
});

afterEach(function () {
    if (File::isDirectory($this->scaffoldPath)) {
        File::deleteDirectory($this->scaffoldPath);
    }

});

test('module:make generates a ServiceProvider that uses InvoiceShelf\\Modules\\Registry', function () {
    Artisan::call('module:make', ['name' => [$this->scaffoldModule]]);

    $providerPath = $this->scaffoldPath.'/app/Providers/'.$this->scaffoldModule.'ServiceProvider.php';
    expect(File::exists($providerPath))->toBeTrue();

    $contents = File::get($providerPath);

    expect($contents)->toContain('use InvoiceShelf\\Modules\\Registry as ModuleRegistry;');
    expect($contents)->toContain('use InvoiceShelf\\Modules\\Support\\ModuleServiceProvider;');
    expect($contents)->toContain('ModuleRegistry::registerMenu(');
    expect($contents)->toContain('ModuleRegistry::registerSettings(');
    expect($contents)->toContain("protected string \$name = '{$this->scaffoldModule}';");
    expect($contents)->toContain('Str::kebab($this->name)');
});

test('module:make generates a composer.json that requires invoiceshelf/modules', function () {
    Artisan::call('module:make', ['name' => [$this->scaffoldModule]]);

    $composerPath = $this->scaffoldPath.'/composer.json';
    expect(File::exists($composerPath))->toBeTrue();

    $manifest = json_decode(File::get($composerPath), true);

    expect($manifest['require'] ?? [])->toHaveKey('invoiceshelf/modules');
    expect($manifest['require']['invoiceshelf/modules'])->toBe('^3.4');
});

test('module:make generates starter lang files for menu and settings', function () {
    Artisan::call('module:make', ['name' => [$this->scaffoldModule]]);

    expect(File::exists($this->scaffoldPath.'/lang/en/menu.php'))->toBeTrue();
    expect(File::exists($this->scaffoldPath.'/lang/en/settings.php'))->toBeTrue();

    $menu = require $this->scaffoldPath.'/lang/en/menu.php';
    expect($menu)->toHaveKey('title');
    expect($menu['title'])->toBe('ScaffoldProbe');

    $settings = require $this->scaffoldPath.'/lang/en/settings.php';
    expect($settings)->toHaveKey('general_section');
    expect($settings)->toHaveKey('enabled');
});
