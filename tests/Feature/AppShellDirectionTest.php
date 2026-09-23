<?php

use Illuminate\Support\Facades\Artisan;

/**
 * The Blade shell paints before the SPA loads a language, so an install
 * whose default language reads right to left must already say so.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $this->withoutVite();
});

test('the app shell reads left to right by default', function () {
    expect(view('app')->render())->toContain('dir="ltr"');
});

test('the app shell reads right to left when the install language does', function (string $locale) {
    app()->setLocale($locale);

    expect(view('app')->render())->toContain('dir="rtl"');
})->with(['ar', 'fa', 'he', 'ur']);

test('regional variants of left to right languages stay left to right', function () {
    app()->setLocale('pt_BR');

    expect(view('app')->render())->toContain('dir="ltr"')->toContain('lang="pt-BR"');
});
