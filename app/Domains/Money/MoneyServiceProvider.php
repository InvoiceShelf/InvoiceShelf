<?php

namespace App\Domains\Money;

use App\Adapters\Money\EloquentExchangeRateBackfill;
use App\Domains\Money\Console\SyncCurrencies;
use App\Domains\Money\Contracts\ExchangeRateBackfill;
use App\Domains\Money\ExchangeRates\CurrencyConverterDriver;
use App\Domains\Money\ExchangeRates\CurrencyFreakDriver;
use App\Domains\Money\ExchangeRates\CurrencyLayerDriver;
use App\Domains\Money\ExchangeRates\OpenExchangeRateDriver;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Money\Policies\ExchangeRateProviderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Modules\Registry;

class MoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExchangeRateBackfill::class, EloquentExchangeRateBackfill::class);
    }

    public function boot(): void
    {
        Gate::policy(ExchangeRateProvider::class, ExchangeRateProviderPolicy::class);

        $this->registerExchangeRateDrivers();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCurrencies::class,
            ]);
        }
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
