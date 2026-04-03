<?php

namespace App\Services\Pdf;

use App;

class PdfDriverFactory
{
    public static function create(string $driver)
    {
        return match ($driver) {
            'dompdf' => App::make('dompdf.wrapper'),
            'gotenberg' => new GotenbergPdfDriver,
            default => throw new \InvalidArgumentException('Invalid PdfDriver requested')
        };
    }
}
