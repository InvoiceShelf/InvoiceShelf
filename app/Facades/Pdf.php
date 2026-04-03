<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Psr\Http\Message\ResponseInterface loadView(string $template)
 */
class Pdf extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'pdf.driver';
    }
}
