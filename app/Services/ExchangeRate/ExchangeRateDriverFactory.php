<?php

namespace App\Services\ExchangeRate;

use InvalidArgumentException;
use InvoiceShelf\Modules\Registry;

class ExchangeRateDriverFactory
{
    /**
     * Built-in driver fallback map.
     *
     * Kept as a backstop so that direct calls to register() (without going through
     * the module Registry) continue to work. Built-in drivers are also registered
     * via the Registry by DriverRegistryProvider — that registration is the
     * canonical source for driver metadata (label, website, config_fields).
     *
     * @var array<string, class-string<ExchangeRateDriver>>
     */
    protected static array $drivers = [
        'currency_freak' => CurrencyFreakDriver::class,
        'currency_layer' => CurrencyLayerDriver::class,
        'open_exchange_rate' => OpenExchangeRateDriver::class,
        'currency_converter' => CurrencyConverterDriver::class,
    ];

    /**
     * Register a custom exchange rate driver directly with the factory.
     *
     * Modules should prefer Registry::registerExchangeRateDriver() instead, which
     * carries metadata (label, website, config_fields) the frontend needs to render
     * a configuration form for the driver.
     */
    public static function register(string $name, string $driverClass): void
    {
        static::$drivers[$name] = $driverClass;
    }

    public static function make(string $driver, string $apiKey, array $config = []): ExchangeRateDriver
    {
        $class = static::resolveDriverClass($driver);

        if (! $class) {
            throw new InvalidArgumentException("Unknown exchange rate driver: {$driver}");
        }

        return new $class($apiKey, $config);
    }

    /**
     * Get all known driver names — both built-in/factory-registered and Registry-registered.
     *
     * @return array<int, string>
     */
    public static function availableDrivers(): array
    {
        $local = array_keys(static::$drivers);
        $registry = array_keys(Registry::allDrivers('exchange_rate'));

        return array_values(array_unique(array_merge($local, $registry)));
    }

    /**
     * Resolve a driver name to its concrete class.
     *
     * Checks the local $drivers map first (built-ins and factory::register() calls),
     * then falls back to the module Registry.
     */
    protected static function resolveDriverClass(string $driver): ?string
    {
        if (isset(static::$drivers[$driver])) {
            return static::$drivers[$driver];
        }

        $meta = Registry::driverMeta('exchange_rate', $driver);

        return $meta['class'] ?? null;
    }
}
