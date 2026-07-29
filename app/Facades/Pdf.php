<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Support\Pdf\ResponseStream loadView(string $template, array $metadata = [])
 */
class Pdf extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'pdf.driver';
    }
}
