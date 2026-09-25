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
    | Fonts
    |--------------------------------------------------------------------------
    | Scripts beyond Latin, Greek and Cyrillic (CJK, Arabic, Hebrew, ...) use
    | font packages that are downloaded the first time a document needs them.
    | An image can bake them in instead with `pdf:fonts:install --all
    | --path=...`: name that directory here (it must sit under the application
    | directory, dompdf's chroot) and turn run-time downloads off.
    |
    */

    'fonts' => [
        'path' => env('PDF_FONTS_PATH'),
        'download' => (bool) env('PDF_FONTS_DOWNLOAD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Setup
    |--------------------------------------------------------------------------
    | Geometry applied to every document, whichever driver renders it. Sizes and
    | margins are CSS lengths (pt, px, pc, mm, cm, in) because that is the only
    | notation both drivers accept without loss — Gotenberg has no named sizes,
    | and dompdf's points array can express anything a name can.
    |
    | Margins default to zero because the stock templates own their own spacing:
    | they carry their own 30px insets, and invoice2/estimate2 are built around a
    | header band that runs to the paper edge, which only bleeds when the page
    | margin is nothing. A custom template owns its insets the same way.
    |
    | Note dompdf's user-agent stylesheet applies 1.2cm of its own unless a @page
    | rule says otherwise, which DompdfDriver now always injects -- so zero here
    | really is zero on both drivers.
    |
    | Setting a margin still works and is honoured by both, at the cost of the
    | band no longer reaching the edge. Page numbers need a bottom margin to draw
    | in, since Chromium renders the footer inside it.
    |
    */

    'page' => [
        'paper_width' => env('PDF_PAPER_WIDTH', '210mm'),
        'paper_height' => env('PDF_PAPER_HEIGHT', '297mm'),
        'orientation' => env('PDF_ORIENTATION', 'portrait'),
        'margin_top' => env('PDF_MARGIN_TOP', '0'),
        'margin_right' => env('PDF_MARGIN_RIGHT', '0'),
        'margin_bottom' => env('PDF_MARGIN_BOTTOM', '0'),
        'margin_left' => env('PDF_MARGIN_LEFT', '0'),

        /*
         * The page margin used by the report templates only, applied on all four
         * sides. Reports need their own knob because the document margins above
         * default to zero for reasons that do not apply to them: invoice2 and
         * estimate2 bleed a coloured header band to the paper edge, and the stock
         * document templates carry their own 30px/50px insets. The report
         * templates carry none at all, they were drawn against dompdf's built-in
         * 1.2cm default, so at margin zero their content sits flush against the
         * paper. Reports are also the most likely to run to several pages, and a
         * page margin is the only inset that repeats on every one of them (body
         * padding insets the first page only). Keeping it separate means changing
         * the document margins does not silently reflow the reports.
         */
        'report_margin' => env('PDF_REPORT_MARGIN', '1.2cm'),

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
             * Archival conformance, converted by LibreOffice inside the Gotenberg
             * image. Empty means an ordinary PDF. PDF/A-3 is what the EU
             * e-invoicing formats ask for. Gotenberg only: dompdf cannot produce
             * PDF/A.
             */
            'pdfa' => env('GOTENBERG_PDFA'),

            // A private Gotenberg host is exempted in config/network.php
            // (GOTENBERG_ALLOWED_PRIVATE_HOST), never implicitly.
        ],
    ],

];
