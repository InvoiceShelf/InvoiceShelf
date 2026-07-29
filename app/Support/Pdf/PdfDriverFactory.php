<?php

namespace App\Support\Pdf;

class PdfDriverFactory
{
    public static function create(string $driver): PdfDriver
    {
        return match ($driver) {
            'dompdf' => new DompdfDriver,
            'gotenberg' => new GotenbergPdfDriver,
            default => throw new \InvalidArgumentException('Invalid PdfDriver requested')
        };
    }
}
