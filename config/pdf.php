<?php

return [

    'driver' => env('PDF_DRIVER', 'gotenberg'),

    'gotenberg' => [
        'host' => env('GOTENBERG_HOST', 'http://pdf:3000'),
        'margins' =>  env('GOTENBERG_PAPERSIZE', '0x0x0x0'),
        'papersize' =>  env('GOTENBERG_PAPERSIZE', '210mmx297mm'),
    ],

    'dompdf' => [],

];
