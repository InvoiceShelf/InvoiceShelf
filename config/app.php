<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Laravel Framework Service Providers...
         */
        Lavary\Menu\ServiceProvider::class,

        /*
         * Application Service Providers...
         */
        InvoiceShelf\Providers\AppServiceProvider::class,
        InvoiceShelf\Providers\AuthServiceProvider::class,
        InvoiceShelf\Providers\BroadcastServiceProvider::class,
        InvoiceShelf\Providers\EventServiceProvider::class,
        InvoiceShelf\Providers\RouteServiceProvider::class,
        InvoiceShelf\Providers\DropboxServiceProvider::class,
        InvoiceShelf\Providers\ViewServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'Flash' => Laracasts\Flash\Flash::class,
        'Menu' => Lavary\Menu\Facade::class,
        'Pusher' => Pusher\Pusher::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
