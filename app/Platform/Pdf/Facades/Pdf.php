<?php

namespace App\Platform\Pdf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Platform\Pdf\Rendering\ResponseStream loadView(string $template, array $metadata = [], ?\App\Platform\Pdf\Rendering\PdfPageSetup $page = null, ?\App\Platform\Pdf\Rendering\FacturXAttachment $attachment = null)
 */
class Pdf extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'pdf.driver';
    }
}
