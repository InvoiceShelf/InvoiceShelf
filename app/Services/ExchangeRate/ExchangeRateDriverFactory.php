<?php

namespace App\Services\ExchangeRate;

use InvalidArgumentException;

class ExchangeRateDriverFactory
{
    /** @var array<string, class-string<ExchangeRateDriver>> */
    protected static array $drivers = [
        'currency_freak' => CurrencyFreakDriver::class,
        'currency_layer' => CurrencyLayerDriver::class,
        'open_exchange_rate' => OpenExchangeRateDriver::class,
        'currency_converter' => CurrencyConverterDriver::class,
    ];

    /**
     * Register a custom exchange rate driver (for module extensibility).
     */
    public static function register(string $name, string $driverClass): void
    {
        static::$drivers[$name] = $driverClass;
    }

    public static function make(string $driver, string $apiKey, array $config = []): ExchangeRateDriver
    {
        $class = static::$drivers[$driver] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unknown exchange rate driver: {$driver}");
        }

        return new $class($apiKey, $config);
    }

    /**
     * Get all registered driver names.
     */
    public static function availableDrivers(): array
    {
        return array_keys(static::$drivers);
    }
}
