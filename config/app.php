<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. You may add any additional class aliases which should
    | be loaded to the array. For speed, all aliases are lazy loaded.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'Menu' => Lavary\Menu\Facade::class,
    ])->toArray(),

];
