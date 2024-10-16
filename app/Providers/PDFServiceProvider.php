<?php

namespace App\Providers;

use App\Services\PDFService;
use Illuminate\Support\ServiceProvider;

class PDFServiceProvider extends ServiceProvider
{
    public $bindings = [
        'pdf.driver' => PDFService::class,
    ];
}
