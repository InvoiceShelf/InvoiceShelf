<?php

use App\Domains\Money\Models\Currency;
use App\Platform\Mcp\Presenters\Money;

test('amounts are exact major-unit decimals', function (int $minor, string $amount) {
    expect(Money::amount($minor))->toBe($amount);
})->with([
    [0, '0.00'],
    [5, '0.05'],
    [123450, '1234.50'],
    [-9, '-0.09'],
    [-123456789, '-1234567.89'],
    [PHP_INT_MAX, '92233720368547758.07'],
]);

test('amounts are written the way the currency writes them', function () {
    $euro = Currency::query()->create([
        'name' => 'Test euro', 'code' => 'TEU', 'symbol' => '€', 'precision' => 2,
        'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true,
    ]);
    $yen = Currency::query()->create([
        'name' => 'Test yen', 'code' => 'TJP', 'symbol' => '¥', 'precision' => 0,
        'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => false,
    ]);

    expect(Money::of(123456, $euro->id))->toBe(['amount' => '1234.56', 'currency' => 'TEU', 'formatted' => '1.234,56€'])
        ->and(Money::of(-500, $euro->id)['formatted'])->toBe('-5,00€')
        ->and(Money::of(123456, $yen->id)['formatted'])->toBe('¥1,235')
        ->and(Money::of(100, null))->toBe(['amount' => '1.00', 'currency' => null, 'formatted' => '1.00']);
});
