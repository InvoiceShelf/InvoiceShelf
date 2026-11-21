<?php

namespace App\Platform\Modules\Infrastructure;

use App\Platform\Pdf\Rendering\QrCode;
use InvoiceShelf\Modules\Contracts\Host\QrCodeGenerator;
use InvoiceShelf\Modules\Qr\ErrorCorrection;

/**
 * QR codes for modules (Module API 1.6.0), drawn by the same renderer as the
 * invoice payment link, so a module never bundles a QR library of its own.
 */
class HostQrCodeGenerator implements QrCodeGenerator
{
    public function svg(
        string $content,
        int $size = 200,
        ErrorCorrection $errorCorrection = ErrorCorrection::Medium,
        int $margin = 4,
    ): string {
        return QrCode::svg($content, $size, $errorCorrection->value, $margin);
    }

    public function svgDataUri(
        string $content,
        int $size = 200,
        ErrorCorrection $errorCorrection = ErrorCorrection::Medium,
        int $margin = 4,
    ): string {
        return QrCode::svgDataUri($content, $size, $errorCorrection->value, $margin);
    }
}
