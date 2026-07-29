<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\App;

/**
 * The dompdf half of {@see PdfDriver}.
 *
 * Previously the factory returned `dompdf.wrapper` straight from the container,
 * which left no place to apply anything InvoiceShelf decides — paper size and
 * orientation were whatever `config/dompdf.php` baked in, and margins were
 * dompdf's own stylesheet default. This class is that place.
 */
class DompdfDriver implements PdfDriver
{
    public function loadView(string $template): ResponseStream
    {
        $page = PdfPageSetup::fromConfig();

        $pdf = $this->wrapper();
        $pdf->setPaper($page->dompdfPaper(), $page->orientation);
        $pdf->loadHTML($this->withPageMargins(view($template)->render(), $page));

        return new DompdfResponse($pdf);
    }

    /**
     * dompdf exposes no margin API — the page box comes from an `@page` rule, so
     * CSS is the only lever (see PdfPageSetup::marginCss).
     *
     * Injected at the top of <head> rather than the bottom so a template that
     * declares its own `@page` still wins, later rules of equal specificity
     * taking precedence. Prepending is the fallback for markup with no <head>,
     * which dompdf tolerates.
     */
    private function withPageMargins(string $html, PdfPageSetup $page): string
    {
        $style = '<style>@page { margin: '.$page->marginCss().'; }</style>';

        $injected = preg_replace('/(<head\b[^>]*>)/i', '$1'.$style, $html, 1, $count);

        return $count ? $injected : $style.$html;
    }

    protected function wrapper(): PDF
    {
        return App::make('dompdf.wrapper');
    }
}
