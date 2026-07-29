<?php

namespace App\Support\Pdf;

interface PdfDriver
{
    /**
     * @param  array<string, string>  $metadata  Document properties written into
     *                                           the file: Title, Author, Subject,
     *                                           Keywords, Creator. Both drivers
     *                                           accept the same key names.
     */
    public function loadView(string $template, array $metadata = []): ResponseStream;
}
