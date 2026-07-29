<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\App;

/**
 * The dompdf half of {@see PdfDriver}.
 *
 * Previously the factory returned `dompdf.wrapper` straight from the container,
 * which left no place to apply anything InvoiceShelf decides — paper size,
 * orientation and margins are all still whatever `config/dompdf.php` baked in at
 * construction. This class is that place.
 */
class DompdfDriver implements PdfDriver
{
    public function loadView(string $template): ResponseStream
    {
        return new DompdfResponse($this->wrapper()->loadView($template));
    }

    protected function wrapper(): PDF
    {
        return App::make('dompdf.wrapper');
    }
}
