<?php

namespace App\Support\Pdf;

class PdfService
{
    public static function loadView(string $template): ResponseStream
    {
        $driver = config('pdf.driver');

        return PdfDriverFactory::create($driver)->loadView($template);
    }
}
