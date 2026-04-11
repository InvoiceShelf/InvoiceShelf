<?php

namespace App\Providers;

use App\Services\ExchangeRate\CurrencyConverterDriver;
use App\Services\ExchangeRate\CurrencyFreakDriver;
use App\Services\ExchangeRate\CurrencyLayerDriver;
use App\Services\ExchangeRate\OpenExchangeRateDriver;
use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Modules\Registry;

class DriverRegistryProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerExchangeRateDrivers();
    }

    protected function registerExchangeRateDrivers(): void
    {
        Registry::registerExchangeRateDriver('currency_converter', [
            'class' => CurrencyConverterDriver::class,
            'label' => 'settings.exchange_rate.currency_converter',
            'website' => 'https://www.currencyconverterapi.com',
            'config_fields' => [
                [
                    'key' => 'type',
                    'type' => 'select',
                    'label' => 'settings.exchange_rate.server',
                    'options' => [
                        ['label' => 'settings.preferences.premium', 'value' => 'PREMIUM'],
                        ['label' => 'settings.preferences.prepaid', 'value' => 'PREPAID'],
                        ['label' => 'settings.preferences.free', 'value' => 'FREE'],
                        ['label' => 'settings.preferences.dedicated', 'value' => 'DEDICATED'],
                    ],
                    'default' => 'FREE',
                ],
                [
                    'key' => 'url',
                    'type' => 'text',
                    'label' => 'settings.exchange_rate.url',
                    'visible_when' => ['type' => 'DEDICATED'],
                ],
            ],
        ]);

        Registry::registerExchangeRateDriver('currency_freak', [
            'class' => CurrencyFreakDriver::class,
            'label' => 'settings.exchange_rate.currency_freak',
            'website' => 'https://currencyfreaks.com',
        ]);

        Registry::registerExchangeRateDriver('currency_layer', [
            'class' => CurrencyLayerDriver::class,
            'label' => 'settings.exchange_rate.currency_layer',
            'website' => 'https://currencylayer.com',
        ]);

        Registry::registerExchangeRateDriver('open_exchange_rate', [
            'class' => OpenExchangeRateDriver::class,
            'label' => 'settings.exchange_rate.open_exchange_rate',
            'website' => 'https://openexchangerates.org',
        ]);
    }
}
