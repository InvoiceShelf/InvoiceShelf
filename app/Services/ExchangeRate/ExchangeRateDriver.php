<?php

namespace App\Services\ExchangeRate;

abstract class ExchangeRateDriver
{
    public function __construct(
        protected string $apiKey,
        protected array $config = [],
    ) {}

    /**
     * Fetch exchange rate between two currencies.
     *
     * @return array Exchange rate values
     */
    abstract public function getExchangeRate(string $baseCurrency, string $targetCurrency): array;

    /**
     * Get list of currencies supported by this provider.
     *
     * @return array Currency codes
     */
    abstract public function getSupportedCurrencies(): array;

    /**
     * Validate that the API key and connection work.
     *
     * @return array Exchange rate values (proof of working connection)
     *
     * @throws ExchangeRateException
     */
    abstract public function validateConnection(): array;
}
