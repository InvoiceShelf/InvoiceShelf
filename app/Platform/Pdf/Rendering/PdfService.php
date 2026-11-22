<?php

namespace App\Platform\Pdf\Rendering;

class PdfService
{
    public static function loadView(
        string $template,
        array $metadata = [],
        ?PdfPageSetup $page = null,
        ?FacturXAttachment $attachment = null,
    ): ResponseStream {
        $driver = config('pdf.driver');

        return PdfDriverFactory::create($driver)->loadView($template, $metadata, $page, $attachment);
    }
}
