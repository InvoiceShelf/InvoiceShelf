<?php

use App\Support\SpaTranslations;

test('it reads a nested key from the locale file', function () {
    expect(SpaTranslations::get('en', 'invoices.new_invoice'))->toBe('New Invoice')
        ->and(SpaTranslations::get('de', 'invoices.new_invoice'))->toBe('Neue Rechnung');
});

test('it maps locale codes the way the SPA names its files', function () {
    expect(SpaTranslations::get('pt_BR', 'invoices.new_invoice'))->toBe('Nova Fatura');
});

test('it falls back to English, then to the key', function () {
    expect(SpaTranslations::get('xx', 'invoices.new_invoice'))->toBe('New Invoice')
        ->and(SpaTranslations::get('../en', 'invoices.new_invoice'))->toBe('New Invoice')
        ->and(SpaTranslations::get('en', 'invoices.no_such_key'))->toBe('invoices.no_such_key');
});

test('it fills placeholders the way vue-i18n does', function () {
    expect(SpaTranslations::get('en', 'mcp.consent.title', ['client' => 'Claude']))->toBe('Connect Claude');
});
