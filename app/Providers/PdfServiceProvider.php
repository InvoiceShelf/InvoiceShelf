<?php

namespace App\Providers;

use App\Services\Pdf\PdfService;
use Illuminate\Support\ServiceProvider;

class PdfServiceProvider extends ServiceProvider
{
    public $bindings = [
        'pdf.driver' => PdfService::class,
    ];
}
