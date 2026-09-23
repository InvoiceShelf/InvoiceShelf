<?php

namespace App\Support;

/**
 * Amounts written by people, or by an AI on their behalf, in the minor units
 * the app stores: "19.99" is 1999.
 */
final class MinorUnits
{
    /**
     * An amount in major units, as a decimal string or a number, in minor
     * units. Null when it is not an amount with at most two decimals.
     */
    public static function fromMajor(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        if (! is_string($value) || preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($value), $match) !== 1) {
            return null;
        }

        $minor = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');

        return $match[1] === '-' ? -$minor : $minor;
    }
}
