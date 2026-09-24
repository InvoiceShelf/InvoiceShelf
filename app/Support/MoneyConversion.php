<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class MoneyConversion
{
    /**
     * Convert a stored minor-unit amount to company currency. Rates remain
     * fractional; persisted monetary amounts use whole minor units, rounded
     * half away from zero like DocumentTotals and payment balances.
     *
     * Null retains the legacy arithmetic behavior for optional amounts/rates.
     * Selecting a rate, including the same-currency default, belongs to callers.
     */
    public static function toBaseMinor(int|float|string|null $amount, int|float|string|null $rate): int
    {
        $amount ??= 0;
        $rate ??= 0;

        if (! is_numeric($amount) || ! is_numeric($rate)) {
            throw new InvalidArgumentException('Currency conversion requires numeric amounts and rates.');
        }

        $converted = $amount * $rate;
        if (is_int($converted)) {
            return $converted;
        }
        if (! is_finite($converted)) {
            throw new InvalidArgumentException('Currency conversion must produce a finite amount.');
        }

        $rounded = round($converted, 0, PHP_ROUND_HALF_UP);
        // The floating-point representation of PHP_INT_MAX rounds up to 2^63.
        // Exact integer products took the branch above; this boundary cannot
        // safely be cast back to a signed integer.
        if ($rounded >= (float) PHP_INT_MAX || $rounded < (float) PHP_INT_MIN) {
            throw new OverflowException('Converted amount exceeds the integer money range.');
        }

        return (int) $rounded;
    }
}
