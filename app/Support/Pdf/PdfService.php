<?php

namespace App\Support\Pdf;

class PdfService
{
    public static function loadView(string $template, array $metadata = []): ResponseStream
    {
        $driver = config('pdf.driver');

        return PdfDriverFactory::create($driver)->loadView($template, $metadata);
    }
}
