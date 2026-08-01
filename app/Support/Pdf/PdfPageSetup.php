<?php

namespace App\Support\Pdf;

/**
 * The page geometry both drivers render to, resolved once and translated per driver.
 *
 * Paper size used to be a Gotenberg-only setting stored as "210mm 297mm", while
 * dompdf was pinned to whatever `config/dompdf.php` said and had no admin control
 * at all. The two also disagreed about margins: dompdf falls back to its own
 * stylesheet default of 1.2cm, Gotenberg was hardcoded to zero, so the same
 * template came out differently depending on the driver. Both now default to
 * nothing: the stock templates carry their own insets, and invoice2/estimate2
 * are built around a header band that only reaches the paper edge at margin 0.
 *
 * Dimensions are stored as CSS lengths because that is the only representation
 * both drivers take without loss. Gotenberg has no notion of named sizes, only
 * dimensions; dompdf accepts either a name from its own 66-entry table or a
 * points array, and the points array is the branch that can express anything.
 * Named presets are a convenience in the UI that resolve to a pair of lengths.
 */
final class PdfPageSetup
{
    /** Points per unit. CSS px is 1/96in, PDF points are 1/72in. */
    private const POINTS_PER_UNIT = [
        'pt' => 1.0,
        'px' => 0.75,
        'pc' => 12.0,
        'mm' => 72 / 25.4,
        'cm' => 720 / 25.4,
        'in' => 72.0,
    ];

    private function __construct(
        public readonly string $width,
        public readonly string $height,
        public readonly string $orientation,
        public readonly string $marginTop,
        public readonly string $marginRight,
        public readonly string $marginBottom,
        public readonly string $marginLeft,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            width: self::length('pdf.page.paper_width', '210mm'),
            height: self::length('pdf.page.paper_height', '297mm'),
            orientation: config('pdf.page.orientation') === 'landscape' ? 'landscape' : 'portrait',
            marginTop: self::length('pdf.page.margin_top', '0'),
            marginRight: self::length('pdf.page.margin_right', '0'),
            marginBottom: self::length('pdf.page.margin_bottom', '0'),
            marginLeft: self::length('pdf.page.margin_left', '0'),
        );
    }

    public function isLandscape(): bool
    {
        return $this->orientation === 'landscape';
    }

    /**
     * Portrait dimensions for Gotenberg's paperSize(). Orientation is applied
     * separately via landscape(), which does the swap itself.
     *
     * @return array{0: string, 1: string}
     */
    public function gotenbergPaper(): array
    {
        return [$this->width, $this->height];
    }

    /**
     * Gotenberg's margins() takes top, bottom, left, right — note the order,
     * which is not the CSS one.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    public function gotenbergMargins(): array
    {
        return [$this->marginTop, $this->marginBottom, $this->marginLeft, $this->marginRight];
    }

    /**
     * Points array for dompdf's setPaper(). Always portrait: Dompdf::getPaperSize()
     * swaps the axes itself when the orientation argument says landscape, so
     * pre-swapping here would cancel out.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function dompdfPaper(): array
    {
        return [0.0, 0.0, self::toPoints($this->width), self::toPoints($this->height)];
    }

    /**
     * dompdf has no margin API at all — margins come from the `@page` box, so
     * the only lever is CSS. See DompdfDriver, which injects this.
     */
    public function marginCss(): string
    {
        return "{$this->marginTop} {$this->marginRight} {$this->marginBottom} {$this->marginLeft}";
    }

    public static function toPoints(string $length): float
    {
        $length = trim($length);

        if ($length === '0') {
            return 0.0;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)(pt|px|pc|mm|cm|in)$/', $length, $m)) {
            throw new \InvalidArgumentException("Invalid PDF page length: {$length}");
        }

        return (float) $m[1] * self::POINTS_PER_UNIT[$m[2]];
    }

    /**
     * Unset or blank falls back to the default; anything set but malformed
     * throws.
     *
     * Values are validated on save, but config can also come from the
     * environment, and the drivers would fail differently otherwise: dompdf
     * throws while converting to points, whereas Gotenberg would forward the
     * garbage and render at some other size. Failing here keeps them consistent
     * and names the offending key.
     */
    private static function length(string $key, string $fallback): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        $value = trim($value);

        if (! preg_match('/^(0|\d+(\.\d+)?(pt|px|pc|mm|cm|in))$/', $value)) {
            throw new \InvalidArgumentException(
                "Invalid PDF page length for {$key}: \"{$value}\". Expected 0, or a number and a unit, e.g. \"210mm\"."
            );
        }

        return $value;
    }
}
