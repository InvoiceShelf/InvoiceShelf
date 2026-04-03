<?php

namespace App\Services\Pdf;

use Illuminate\Http\Response;

interface ResponseStream
{
    public function stream(string $filename): Response;

    public function output(): string;
}
