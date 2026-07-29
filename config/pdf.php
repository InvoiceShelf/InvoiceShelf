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
    | Page Setup
    |--------------------------------------------------------------------------
    | Geometry applied to every document, whichever driver renders it. Sizes and
    | margins are CSS lengths (pt, px, pc, mm, cm, in) because that is the only
    | notation both drivers accept without loss — Gotenberg has no named sizes,
    | and dompdf's points array can express anything a name can.
    |
    | The 1.2cm margin default is dompdf's own, from its user-agent stylesheet.
    | Gotenberg used to be hardcoded to zero, so the same template came out
    | edge-to-edge on one driver and inset on the other; matching dompdf keeps
    | existing documents looking as they always have.
    |
    */

    'page' => [
        'paper_width' => env('PDF_PAPER_WIDTH', '210mm'),
        'paper_height' => env('PDF_PAPER_HEIGHT', '297mm'),
        'orientation' => env('PDF_ORIENTATION', 'portrait'),
        'margin_top' => env('PDF_MARGIN_TOP', '1.2cm'),
        'margin_right' => env('PDF_MARGIN_RIGHT', '1.2cm'),
        'margin_bottom' => env('PDF_MARGIN_BOTTOM', '1.2cm'),
        'margin_left' => env('PDF_MARGIN_LEFT', '1.2cm'),

        /*
         * Repeat "page / total" at the foot of every page. Gotenberg only:
         * Chromium repeats a footer template and substitutes the counts, and
         * dompdf has no equivalent. Off by default so existing documents are
         * unchanged. The footer draws inside the bottom margin, so it needs one.
         */
        'page_numbers' => env('PDF_PAGE_NUMBERS', false),
    ],

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
