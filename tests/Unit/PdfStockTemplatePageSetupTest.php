<?php

/**
 * Stock templates must leave page geometry to the driver. An html margin
 * overrides dompdf's injected @page rule, while Chromium applies its default
 * body margin unless it is reset. Invoice and estimate headers also have to
 * remain in the printable content box when an operator enables page margins.
 */
function stockTemplateCssRules(string $source, string $selector): string
{
    preg_match(
        '/'.preg_quote($selector, '/').'\s*\{(?<rules>[^}]*)\}/',
        $source,
        $matches
    );

    return $matches['rules'] ?? '';
}

test('stock templates reset body margins without overriding page margins', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));
    $bodyRules = stockTemplateCssRules($source, 'body');

    expect($bodyRules)->toContain('margin: 0px;')
        ->and($source)
        ->not->toContain('html {');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
    'payment/payment',
    'reports/expenses',
    'reports/profit-loss',
    'reports/sales-customers',
    'reports/sales-items',
    'reports/tax-summary',
]);

test('stock invoice and estimate headers never move above the printable content box', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));
    $headerRules = stockTemplateCssRules($source, '.header-container');

    expect($headerRules)->toContain('position: relative;')
        ->not->toMatch('/(?:top|margin-top):\\s*-\\d+/');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);

/**
 * The stock templates set `table { border-collapse: collapse }`, and CSS says
 * padding does not apply to a table in that mode. dompdf applies it anyway;
 * Chromium follows the spec and drops it, which put the two renderers 22.5pt
 * apart on each side of the items table. The spacing belongs on the wrapper,
 * which is a plain block both engines treat the same.
 */
test('the items table carries no padding of its own', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));

    expect(stockTemplateCssRules($source, '.items-table'))->not->toContain('padding')
        ->and(stockTemplateCssRules($source, '.items-table-wrapper'))->toContain('padding');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);

test('stock invoice and estimate templates expose the shared item-table hook', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));

    expect($source)->toContain('class="items-table-wrapper"')
        ->toContain("@include('app.pdf.".dirname($template).".partials.table')");
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);
