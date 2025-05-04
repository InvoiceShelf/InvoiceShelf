<?php

return [

    'driver' => env('PDF_DRIVER', 'gotenberg'),

    'gotenberg' => [
        'host' => env('GOTENBERG_HOST', 'http://pdf:3000'),
        'papersize' => env('GOTENBERG_PAPERSIZE', '210mm 297mm'),
    ],

    'dompdf' => [],

];
