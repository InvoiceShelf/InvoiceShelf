<?php

use App\Providers\AppConfigProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DropboxServiceProvider;
use App\Providers\PdfServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\ViewServiceProvider;
use App\Services\Hashids\HashidsServiceProvider;

return [
    HashidsServiceProvider::class,
    AppServiceProvider::class,
    RouteServiceProvider::class,
    DropboxServiceProvider::class,
    ViewServiceProvider::class,
    PdfServiceProvider::class,
    AppConfigProvider::class,
];
