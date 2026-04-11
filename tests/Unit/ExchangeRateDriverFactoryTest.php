<?php

use App\Services\ExchangeRate\CurrencyFreakDriver;
use App\Services\ExchangeRate\ExchangeRateDriver;
use App\Services\ExchangeRate\ExchangeRateDriverFactory;
use InvoiceShelf\Modules\Registry;

test('make resolves built-in drivers from the factory map', function () {
    $driver = ExchangeRateDriverFactory::make('currency_freak', 'fake-key');

    expect($driver)->toBeInstanceOf(CurrencyFreakDriver::class);
});

test('make resolves Registry-only drivers via metadata', function () {
    $fakeClass = new class('', []) extends ExchangeRateDriver
    {
        public function getExchangeRate(string $baseCurrency, string $targetCurrency): array
        {
            return [];
        }

        public function getSupportedCurrencies(): array
        {
            return [];
        }

        public function validateConnection(): array
        {
            return [];
        }
    };

    Registry::registerExchangeRateDriver('registry_only_driver', [
        'class' => $fakeClass::class,
        'label' => 'test.label',
    ]);

    try {
        $driver = ExchangeRateDriverFactory::make('registry_only_driver', 'fake-key');
        expect($driver)->toBeInstanceOf(ExchangeRateDriver::class);
    } finally {
        unset(Registry::$drivers['exchange_rate']['registry_only_driver']);
    }
});

test('make throws for unknown drivers', function () {
    expect(fn () => ExchangeRateDriverFactory::make('definitely_not_a_real_driver', 'k'))
        ->toThrow(InvalidArgumentException::class);
});

test('availableDrivers merges built-in and Registry-registered drivers', function () {
    Registry::registerExchangeRateDriver('extra_driver', [
        'class' => CurrencyFreakDriver::class,
        'label' => 'extra.label',
    ]);

    try {
        $available = ExchangeRateDriverFactory::availableDrivers();

        expect($available)
            ->toContain('currency_freak')
            ->toContain('currency_converter')
            ->toContain('extra_driver');
    } finally {
        unset(Registry::$drivers['exchange_rate']['extra_driver']);
    }
});
