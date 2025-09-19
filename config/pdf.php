<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default PDF Driver
    |--------------------------------------------------------------------------
    | Here you may specify which of the PDF drivers below you wish to use as
    | your default driver for all PDF generation.
    |
    */

    'driver' => env('PDF_DRIVER', 'dompdf'),

    /*
    |--------------------------------------------------------------------------
    | PDF Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application. Example
    | configuration has been included, but you may add as many connections as
    | you would like.
    |
    */
    'connections' => [

        'dompdf' => [],

        'gotenberg' => [
            'host' => env('GOTENBERG_HOST', 'http://pdf:3000'),
            'papersize' => env('GOTENBERG_PAPERSIZE', '210mm 297mm'),
        ],
    ],

];
