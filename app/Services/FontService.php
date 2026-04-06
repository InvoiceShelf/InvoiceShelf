<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class FontService
{
    /**
     * Font packages available for on-demand download.
     *
     * Each package downloads STATIC TrueType fonts (not variable fonts) — dompdf's
     * PHP-Font-Lib does not support variable fonts (`fvar`/`gvar` tables), which is
     * why Google Fonts' `NotoSansTC[wght].ttf` produces empty boxes when registered.
     * Source: https://github.com/life888888/cjk-fonts-ttf — official Adobe Source Han
     * Sans / Noto Sans CJK rebuilt as static TTF in Regular + Bold weights.
     */
    public const FONT_PACKAGES = [
        'noto-sans-sc' => [
            'name' => 'Noto Sans Simplified Chinese',
            'family' => 'NotoSansCJKsc',
            'locales' => ['zh_CN'],
            'size' => '~32MB',
            'files' => [
                [
                    'file' => 'NotoSansCJKsc-Regular.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKsc-Regular.ttf',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKsc-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKsc-Bold.ttf',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'noto-sans-tc' => [
            'name' => 'Noto Sans Traditional Chinese',
            'family' => 'NotoSansCJKtc',
            'locales' => ['zh'],
            'size' => '~32MB',
            'files' => [
                [
                    'file' => 'NotoSansCJKtc-Regular.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKtc-Regular.ttf',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKtc-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKtc-Bold.ttf',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'noto-sans-jp' => [
            'name' => 'Noto Sans Japanese',
            'family' => 'NotoSansCJKjp',
            'locales' => ['ja'],
            'size' => '~32MB',
            'files' => [
                [
                    'file' => 'NotoSansCJKjp-Regular.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKjp-Regular.ttf',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKjp-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKjp-Bold.ttf',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'noto-sans-kr' => [
            'name' => 'Noto Sans Korean',
            'family' => 'NotoSansCJKkr',
            'locales' => ['ko'],
            'size' => '~32MB',
            'files' => [
                [
                    'file' => 'NotoSansCJKkr-Regular.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKkr-Regular.ttf',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKkr-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKkr-Bold.ttf',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
    ];

    /**
     * Check if a locale requires an on-demand font download.
     */
    public function needsDownload(string $locale): bool
    {
        $package = $this->getPackageForLocale($locale);

        return $package && ! $this->isInstalled($package);
    }

    /**
     * Ensure fonts are available for the given locale.
     * Downloads synchronously if not already installed (blocking fallback).
     */
    public function ensureFontsForLocale(string $locale): void
    {
        $package = $this->getPackageForLocale($locale);

        if (! $package) {
            return;
        }

        if (! $this->isInstalled($package)) {
            $this->downloadPackage($package);
        }
    }

    /**
     * Check if a font package is installed (all files present).
     */
    public function isInstalled(array $package): bool
    {
        foreach ($package['files'] as $entry) {
            if (! File::exists(storage_path('fonts/'.$entry['file']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Download a font package to storage/fonts/.
     */
    public function downloadPackage(array $package): void
    {
        $fontsDir = storage_path('fonts');

        if (! File::isDirectory($fontsDir)) {
            File::makeDirectory($fontsDir, 0755, true);
        }

        foreach ($package['files'] as $entry) {
            $targetPath = $fontsDir.'/'.$entry['file'];

            if (File::exists($targetPath)) {
                continue;
            }

            $response = Http::timeout(180)->get($entry['url']);

            if ($response->successful()) {
                File::put($targetPath, $response->body());
            }
        }
    }

    /**
     * Get the status of all font packages (for the admin UI).
     */
    public function getPackageStatuses(): array
    {
        $statuses = [];

        foreach (self::FONT_PACKAGES as $key => $package) {
            $statuses[] = [
                'key' => $key,
                'name' => $package['name'],
                'family' => $package['family'],
                'locales' => $package['locales'],
                'size' => $package['size'],
                'installed' => $this->isInstalled($package),
            ];
        }

        return $statuses;
    }

    /**
     * Generate @font-face CSS rules for all installed on-demand fonts.
     * Used by the PDF fonts partial so dompdf can resolve CJK families
     * via standard CSS — no separate registerFont() dance required.
     */
    public function getInstalledFontFaces(): array
    {
        $faces = [];
        $fontsDir = storage_path('fonts');

        foreach (self::FONT_PACKAGES as $package) {
            if (! $this->isInstalled($package)) {
                continue;
            }

            foreach ($package['files'] as $entry) {
                $filePath = $fontsDir.'/'.$entry['file'];

                $faces[] = "@font-face {
                font-family: '{$package['family']}';
                font-style: {$entry['style']};
                font-weight: {$entry['weight']};
                src: url(\"{$filePath}\") format('truetype');
            }";
            }
        }

        return $faces;
    }

    /**
     * Get the primary font family for the current locale.
     *
     * DomPDF doesn't support font-family fallback for missing glyphs —
     * it uses the first font for ALL characters. So CJK locales must
     * use the CJK font as the primary, not as a fallback.
     */
    public function getFontFamilyForLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        // Check if this locale has an installed on-demand font
        $package = $this->getPackageForLocale($locale);
        if ($package && $this->isInstalled($package)) {
            return '"'.$package['family'].'"';
        }

        return '"NotoSans"';
    }

    /**
     * Get the full font-family CSS value for the current locale.
     */
    public function getFontFamilyChain(?string $locale = null): string
    {
        $primary = $this->getFontFamilyForLocale($locale);

        return $primary.', "NotoSans", "DejaVu Sans", sans-serif';
    }

    private function getPackageForLocale(string $locale): ?array
    {
        foreach (self::FONT_PACKAGES as $package) {
            if (in_array($locale, $package['locales'])) {
                return $package;
            }
        }

        return null;
    }
}
