<?php

use App\Support\MoneyConversion;

uses()->group('money-conversion');

test('base currency amounts are rounded to whole minor units', function ($amount, $rate, int $expected) {
    expect(MoneyConversion::toBaseMinor($amount, $rate))->toBe($expected);
})->with([
    'reported SEK conversion' => [10660, '11.257191', 120002],
    'unchanged currency' => [10660, 1, 10660],
    'fractional rate rounds down' => [100, '0.124', 12],
    'fractional rate rounds up' => [101, '0.125', 13],
    'positive half' => [1, '1.5', 2],
    'negative half' => [-1, '1.5', -2],
    'negative amount' => [-10660, '11.257191', -120002],
    'zero amount' => [0, '11.257191', 0],
    'numeric strings' => ['10660', '11.257191', 120002],
    'optional amount' => [null, 1, 0],
    'legacy missing rate' => [10660, null, 0],
    'largest exact integer' => [PHP_INT_MAX, 1, PHP_INT_MAX],
    'smallest exact integer' => [PHP_INT_MIN, 1, PHP_INT_MIN],
]);

test('invalid monetary conversions fail before integer casting', function ($amount, $rate) {
    MoneyConversion::toBaseMinor($amount, $rate);
})->with([
    'infinite result' => [100, INF],
    'not a number' => [100, NAN],
    'invalid rate' => [100, 'invalid'],
])->throws(InvalidArgumentException::class);

test('unrepresentable monetary conversions cannot overflow', function ($amount, $rate) {
    MoneyConversion::toBaseMinor($amount, $rate);
})->with([
    'positive overflow' => [PHP_INT_MAX, 2],
    'negative overflow' => [PHP_INT_MIN, 2],
    'rounded upper bound' => [PHP_INT_MAX, 1.0],
])->throws(OverflowException::class);
