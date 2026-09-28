<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Models\Supplier;
use App\Support\MoneyConversion;
use Illuminate\Validation\ValidationException;

/**
 * Small checks and conversions shared by the purchase services.
 */
final class PurchaseInputs
{
    /**
     * Lock one of the company's suppliers for the rest of the transaction.
     */
    public static function lockSupplier(int $companyId, int $supplierId): Supplier
    {
        return Supplier::query()->forCompany($companyId)->whereKey($supplierId)->lockForUpdate()->firstOrFail();
    }

    /**
     * The id of the company's base currency.
     */
    public static function companyCurrency(int $companyId): int
    {
        return (int) CompanySetting::getSetting('currency', $companyId);
    }

    /**
     * The currency and exchange rate to store on a document. A document in the
     * base currency always gets a rate of 1; any other needs a positive rate.
     */
    public static function money(int $companyId, array $data): array
    {
        $rate = self::companyCurrency($companyId) === (int) $data['currency_id']
            ? 1
            : (float) $data['exchange_rate'];

        self::ensure($rate > 0 && is_finite($rate), 'exchange_rate', 'A positive exchange rate is required.');

        return ['currency_id' => (int) $data['currency_id'], 'exchange_rate' => $rate];
    }

    /**
     * Convert a document-currency amount to base-currency minor units.
     */
    public static function base(int $amount, float|int $rate): int
    {
        return MoneyConversion::toBaseMinor($amount, $rate);
    }

    /**
     * Fail with a validation error on the given field unless the condition holds.
     */
    public static function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}
