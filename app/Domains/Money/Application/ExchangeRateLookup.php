<?php

namespace App\Domains\Money\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\ExchangeRates\ExchangeRateException;
use App\Domains\Money\Models\Currency;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Money\Models\ExchangeRateProvider;

/**
 * Finds the rate that turns an amount in a document currency into the
 * company's own currency, the one the document forms offer when a customer
 * pays in something else.
 *
 * A live quote from an active provider that lists the currency comes first,
 * the last rate logged for the pair second. The provider is picked from every
 * company's providers, not only the asking company's: that is what the rate
 * endpoint has always done, and it is kept as is here.
 */
class ExchangeRateLookup
{
    public function __construct(
        private readonly ExchangeRateProviderService $exchangeRateProviderService,
    ) {}

    /**
     * The quote as the endpoint hands it to the forms: the provider's list of
     * rates, or the logged rate in a list of one. Null when there is neither.
     *
     * @return array<int, mixed>|null
     */
    public function quote(Currency $currency, int|string $companyId): ?array
    {
        $baseCurrency = $this->companyCurrency($companyId);

        $live = $this->liveRate($currency, $baseCurrency);

        if ($live !== null) {
            return $live;
        }

        // Note the column naming: base_currency_id carries the document
        // currency and currency_id the company's base currency.
        $logged = ExchangeRateLog::where('base_currency_id', $currency->id)
            ->where('currency_id', $baseCurrency->id)
            ->latest()
            ->value('exchange_rate');

        return $logged ? [$logged] : null;
    }

    /**
     * The quote's first rate as a number, for callers that need one.
     */
    public function rate(Currency $currency, int|string $companyId): ?float
    {
        $quote = $this->quote($currency, $companyId);
        $rate = $quote === null ? null : reset($quote);

        return is_numeric($rate) ? (float) $rate : null;
    }

    private function companyCurrency(int|string $companyId): Currency
    {
        $settings = CompanySetting::getSettings(['currency'], $companyId);

        return Currency::findOrFail($settings['currency']);
    }

    /**
     * @return array<int, mixed>|null
     */
    private function liveRate(Currency $currency, Currency $baseCurrency): ?array
    {
        $provider = ExchangeRateProvider::whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->first();

        if (! $provider) {
            return null;
        }

        try {
            return $this->exchangeRateProviderService->getExchangeRate(
                $provider->driver,
                $provider->key,
                $provider->driver_config ?? [],
                $currency->code,
                $baseCurrency->code,
            );
        } catch (ExchangeRateException) {
            return null;
        }
    }
}
