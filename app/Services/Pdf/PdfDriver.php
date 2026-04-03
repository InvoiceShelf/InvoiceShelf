<?php

namespace App\Services\Pdf;

interface PdfDriver
{
    public function loadView(string $template): ResponseStream;
}
