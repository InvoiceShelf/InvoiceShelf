<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Models\Supplier;
use App\Support\MoneyConversion;
use Illuminate\Validation\ValidationException;

final class PurchaseInputs
{
    public static function lockSupplier(int $companyId, int $supplierId): Supplier
    {
        return Supplier::query()->forCompany($companyId)->whereKey($supplierId)->lockForUpdate()->firstOrFail();
    }

    public static function money(int $companyId, array $data): array
    {
        $rate = (int) CompanySetting::getSetting('currency', $companyId) === (int) $data['currency_id'] ? 1 : (float) $data['exchange_rate'];
        self::ensure($rate > 0 && is_finite($rate), 'exchange_rate', 'A positive exchange rate is required.');

        return ['currency_id' => (int) $data['currency_id'], 'exchange_rate' => $rate];
    }

    public static function base(int $amount, float|int $rate): int
    {
        return MoneyConversion::toBaseMinor($amount, $rate);
    }

    public static function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}
