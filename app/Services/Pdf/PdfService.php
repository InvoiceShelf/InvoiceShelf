<?php

namespace App\Services\Pdf;

class PdfService
{
    public static function loadView(string $template)
    {
        $driver = config('pdf.driver');

        return PdfDriverFactory::create($driver)->loadView($template);
    }
}
