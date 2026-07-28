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

            /*
             * Gotenberg usually runs as a sidecar on a private network, which the
             * SSRF guard rejects. Name that one host here to exempt it — e.g.
             * GOTENBERG_ALLOWED_PRIVATE_HOST=http://pdf:3000. Only this exact value
             * is exempt; the guard still blocks every other private target, so the
             * host setting cannot be repointed at an internal service. No default:
             * the `host` fallback above must never be trusted implicitly.
             */
            'allowed_private_host' => env('GOTENBERG_ALLOWED_PRIVATE_HOST'),
        ],
    ],

];
