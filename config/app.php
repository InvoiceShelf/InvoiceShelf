<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [


    'aliases' => Facade::defaultAliases()->merge([
        'Flash' => Laracasts\Flash\Flash::class,
        'Menu' => Lavary\Menu\Facade::class,
        'Pusher' => Pusher\Pusher::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
