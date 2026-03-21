<?php

declare(strict_types=1);

namespace App\Hashids;

use Hashids\Hashids as HashidsClient;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class HashidsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('hashids.factory', function () {
            return new HashidsFactory;
        });

        $this->app->alias('hashids.factory', HashidsFactory::class);

        $this->app->singleton('hashids', function (Container $app) {
            return new HashidsManager($app['config'], $app['hashids.factory']);
        });

        $this->app->alias('hashids', HashidsManager::class);

        $this->app->bind('hashids.connection', function (Container $app) {
            return $app['hashids']->connection();
        });

        $this->app->alias('hashids.connection', HashidsClient::class);
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            'hashids',
            'hashids.factory',
            'hashids.connection',
        ];
    }
}
