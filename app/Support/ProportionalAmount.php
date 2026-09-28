<?php

namespace App\Support;

/** Integer apportionment without overflowing amount * part. */
final class ProportionalAmount
{
    public static function floor(int $amount, int $part, int $whole): int
    {
        if ($whole <= 0 || $part <= 0) {
            return 0;
        }
        if ($part >= $whole) {
            return $amount;
        }
        $result = intdiv($amount, $whole) * $part;
        $remainder = $amount % $whole;
        $quotient = 0;
        $modulo = 0;
        foreach (str_split(decbin($part)) as $bit) {
            $quotient *= 2;
            if ($modulo >= $whole - $modulo) {
                $modulo -= $whole - $modulo;
                $quotient++;
            } else {
                $modulo *= 2;
            }
            if ($bit === '1') {
                if ($modulo >= $whole - $remainder) {
                    $modulo -= $whole - $remainder;
                    $quotient++;
                } else {
                    $modulo += $remainder;
                }
            }
        }

        return $result + $quotient;
    }

    public static function slice(int $amount, int $before, int $after, int $whole): int
    {
        return self::floor($amount, $after, $whole) - self::floor($amount, $before, $whole);
    }
}
