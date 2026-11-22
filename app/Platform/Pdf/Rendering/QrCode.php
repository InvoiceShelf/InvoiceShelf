<?php

namespace App\Platform\Pdf\Rendering;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Exception\ExceptionInterface;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * QR codes for documents, drawn as SVG. The invoice payment link uses it, and
 * so do modules, through the SDK's QrCodeGenerator (HostQrCodeGenerator).
 *
 * SVG on purpose: it needs no image extension, stays sharp at any print size,
 * and both PDF drivers draw it (Dompdf through its SVG library, Gotenberg as
 * any browser would).
 *
 * Content is UTF-8 text. ASCII goes in byte for byte. Anything else is encoded
 * as UTF-8 behind an ECI marker rather than converted to ISO-8859-1, the QR
 * default, because the payment payloads that carry names (EPC, Swiss
 * QR-bill) are read as UTF-8 by the apps that scan them.
 */
final class QrCode
{
    /**
     * An SVG document of the code.
     *
     * @param  int  $size  the edge of the square image in pixels, quiet zone included
     * @param  string  $errorCorrection  L, M, Q or H
     * @param  int  $margin  the quiet zone around the code, in modules
     *
     * @throws InvalidArgumentException when the content is empty or does not fit a QR code at that level
     */
    public static function svg(string $content, int $size = 160, string $errorCorrection = 'M', int $margin = 2): string
    {
        if ($content === '') {
            throw new InvalidArgumentException('A QR code needs some content.');
        }

        if ($size < 1 || $margin < 0) {
            throw new InvalidArgumentException('A QR code needs a positive size and a margin of zero or more.');
        }

        $encoding = preg_match('/^[\x00-\x7F]*$/', $content) === 1
            ? Encoder::DEFAULT_BYTE_MODE_ENCODING
            : 'UTF-8';

        $writer = new Writer(new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd));

        try {
            return $writer->writeString($content, $encoding, self::level($errorCorrection));
        } catch (ExceptionInterface $exception) {
            throw new InvalidArgumentException('The content does not fit in a QR code: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * The same SVG as a data URI, for the `src` of an `<img>`: the renderer
     * never fetches anything.
     *
     * @throws InvalidArgumentException when the content is empty or does not fit a QR code at that level
     */
    public static function svgDataUri(string $content, int $size = 160, string $errorCorrection = 'M', int $margin = 2): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($content, $size, $errorCorrection, $margin));
    }

    /**
     * The library's level for a standard letter.
     */
    private static function level(string $letter): ErrorCorrectionLevel
    {
        return match ($letter) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'Q' => ErrorCorrectionLevel::Q(),
            'H' => ErrorCorrectionLevel::H(),
            default => throw new InvalidArgumentException("Unknown error correction level {$letter}; use L, M, Q or H."),
        };
    }
}
