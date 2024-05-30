<?php

return [

    'guards' => [
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
            'hash' => false,
        ],

        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
            'hash' => false,
        ],
    ],

    'providers' => [
        'customers' => [
            'driver' => 'eloquent',
            'model' => \InvoiceShelf\Models\Customer::class,
        ],
    ],

    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

];
