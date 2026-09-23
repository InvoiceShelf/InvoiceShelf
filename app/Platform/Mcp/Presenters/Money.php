<?php

namespace App\Platform\Mcp\Presenters;

use App\Domains\Money\Models\Currency;

/**
 * An amount the way tools return it: the decimal in major units, the
 * currency code, and the amount written the way the company shows it.
 *
 * Amounts are stored in hundredths whatever the currency's precision, and are
 * divided by 100 here the same way the PDFs divide them.
 */
final class Money
{
    /** @var array<int, Currency|null> */
    private static array $currencies = [];

    /**
     * @return array{amount: string, currency: string|null, formatted: string}
     */
    public static function of(int|float|string|null $minor, int|string|null $currencyId): array
    {
        $minor = (int) round((float) $minor);
        $currency = self::currency($currencyId);

        return [
            'amount' => self::amount($minor),
            'currency' => $currency?->code,
            'formatted' => $currency ? self::format($minor, $currency) : self::amount($minor),
        ];
    }

    /**
     * Hundredths as an exact decimal string: 123450 is "1234.50".
     */
    public static function amount(int $minor): string
    {
        $absolute = abs($minor);

        return ($minor < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function format(int $minor, Currency $currency): string
    {
        $digits = number_format(
            abs($minor) / 100,
            (int) $currency->precision,
            (string) $currency->decimal_separator,
            (string) $currency->thousand_separator,
        );

        $rendered = $currency->swap_currency_symbol ? $digits.$currency->symbol : $currency->symbol.$digits;

        return $minor < 0 && preg_match('/[1-9]/', $digits) === 1 ? '-'.$rendered : $rendered;
    }

    private static function currency(int|string|null $id): ?Currency
    {
        if ($id === null || $id === '') {
            return null;
        }

        return self::$currencies[(int) $id] ??= Currency::find($id);
    }
}
