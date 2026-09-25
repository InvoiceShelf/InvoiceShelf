<?php

namespace App\Platform\Pdf\Application;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
     *
     * Every URL names a release or a commit, never a branch, and every file
     * carries the sha256 it must match before it is kept.
     */
    public const FONT_PACKAGES = [
        'noto-sans' => [
            'name' => 'Noto Sans (Latin, Greek, Cyrillic)',
            'family' => 'NotoSans',
            'locales' => [],
            'size' => '~1.7MB',
            'bundled' => true,
            'files' => [
                [
                    'file' => 'NotoSans-Regular.ttf',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSans-Bold.ttf',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSans-Italic.ttf',
                    'weight' => 'normal',
                    'style' => 'italic',
                ],
                [
                    'file' => 'NotoSans-BoldItalic.ttf',
                    'weight' => 'bold',
                    'style' => 'italic',
                ],
            ],
        ],
        'noto-sans-sc' => [
            'name' => 'Noto Sans Simplified Chinese',
            'family' => 'NotoSansCJKsc',
            'locales' => ['zh_CN'],
            'size' => '~32MB',
            'files' => [
                [
                    'file' => 'NotoSansCJKsc-Regular.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKsc-Regular.ttf',
                    'sha256' => 'e0c4dabeced754e5aafd137cd29b1b76ff686e624513c65a000d912b7516daba',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKsc-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKsc-Bold.ttf',
                    'sha256' => 'b4430b3957a5837ff1771b4315ae5c9b59653c8cdae4139340faa70105ddf133',
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
                    'sha256' => 'a14b9a4025816e7a305b2ad432092140c3a1f1f66a6b0f01711f2ec1e3cff85c',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKtc-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKtc-Bold.ttf',
                    'sha256' => 'fb9dc08627730ca563a4e0bbdb59727a1a2bba66c2ffda1ec04c171f154fbde9',
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
                    'sha256' => '81fb4844afed93fa15cd0cd1745637e961424a04c085b2256e8a8a497930e99b',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKjp-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKjp-Bold.ttf',
                    'sha256' => '0db88a3710ebe4c4bf99b940b306a6e359dca4e7bccac5f41984ab96f15b5190',
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
                    'sha256' => '3a0d77708b961208aa4c4a90ac78dd4710ac1b35c62b31a5e555d830441954e8',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansCJKkr-Bold.ttf',
                    'url' => 'https://github.com/life888888/cjk-fonts-ttf/releases/download/v0.1.0/NotoSansCJKkr-Bold.ttf',
                    'sha256' => 'ae4b53d32f5c6308be0fde766ac6c19cdb886fe72c1ca724dad0383cd1c33e34',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'noto-sans-hebrew' => [
            'name' => 'Noto Sans Hebrew',
            'family' => 'NotoSansHebrew',
            'locales' => ['he', 'yi'],
            'size' => '~40KB',
            'files' => [
                [
                    'file' => 'NotoSansHebrew-Regular.ttf',
                    'url' => 'https://github.com/openmaptiles/fonts/raw/a4f9e7cf18aa382945c0a912f3022ba94a0b1d52/noto-sans/NotoSansHebrew-Regular.ttf',
                    'sha256' => 'f9ced0325880ab81fdaca60595c96dbd99cf9ef6fad67540ce14d68993c7493c',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansHebrew-Bold.ttf',
                    'url' => 'https://github.com/openmaptiles/fonts/raw/a4f9e7cf18aa382945c0a912f3022ba94a0b1d52/noto-sans/NotoSansHebrew-Bold.ttf',
                    'sha256' => '342aa0f194ba76b8fddb69c08c74d109f8d9a99db44d4850ee936cea0f84aa8f',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'noto-naskh-arabic' => [
            'name' => 'Noto Naskh Arabic (Arabic, Persian, Urdu)',
            'family' => 'NotoNaskhArabic',
            'locales' => ['ar', 'fa', 'ur', 'ckb'],
            'size' => '~285KB',
            'files' => [
                [
                    'file' => 'NotoNaskhArabic-Regular.ttf',
                    'url' => 'https://github.com/openmaptiles/fonts/raw/a4f9e7cf18aa382945c0a912f3022ba94a0b1d52/noto-sans/NotoNaskhArabic-Regular.ttf',
                    'sha256' => 'd42ab8a21585ca002cc9dc7f05145b3a432acf80b3dcf0d35a022e9f8a45a77a',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoNaskhArabic-Bold.ttf',
                    'url' => 'https://github.com/openmaptiles/fonts/raw/a4f9e7cf18aa382945c0a912f3022ba94a0b1d52/noto-sans/NotoNaskhArabic-Bold.ttf',
                    'sha256' => 'aa3ce60f191d1c7d84a273d49883dd9b3e158b79a2b4ac1420cae10636bbbbaf',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'noto-sans-devanagari' => [
            'name' => 'Noto Sans Devanagari (Hindi, Marathi, Sanskrit, Nepali)',
            'family' => 'NotoSansDevanagari',
            'locales' => ['hi', 'mr', 'sa', 'ne'],
            'size' => '~280KB',
            'files' => [
                [
                    'file' => 'NotoSansDevanagari-Regular.ttf',
                    'url' => 'https://github.com/openmaptiles/fonts/raw/a4f9e7cf18aa382945c0a912f3022ba94a0b1d52/noto-sans/NotoSansDevanagari-Regular.ttf',
                    'sha256' => 'b1dffa1fccb30dc45287111834a9db15c652b05d4d67201abe73e67717017590',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'NotoSansDevanagari-Bold.ttf',
                    'url' => 'https://github.com/openmaptiles/fonts/raw/a4f9e7cf18aa382945c0a912f3022ba94a0b1d52/noto-sans/NotoSansDevanagari-Bold.ttf',
                    'sha256' => '08a37d656cd14282875724800d3b29f335665f9a8156ef9dbf68128bfae709e9',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
        'sarabun' => [
            'name' => 'Sarabun (Thai)',
            'family' => 'Sarabun',
            'locales' => ['th'],
            'size' => '~180KB',
            'files' => [
                [
                    'file' => 'Sarabun-Regular.ttf',
                    'url' => 'https://github.com/google/fonts/raw/7d82a06388d70ec34312df7e7cede76ba8bbf7b5/ofl/sarabun/Sarabun-Regular.ttf',
                    'sha256' => '226d4f368fbc0457990ddef2692679badfd2c1a4e89e5ac4d43c10ba7743b2f1',
                    'weight' => 'normal',
                    'style' => 'normal',
                ],
                [
                    'file' => 'Sarabun-Bold.ttf',
                    'url' => 'https://github.com/google/fonts/raw/7d82a06388d70ec34312df7e7cede76ba8bbf7b5/ofl/sarabun/Sarabun-Bold.ttf',
                    'sha256' => 'd38308ca27d067b9a1b79006337c1f9a66c29aa016d96a9d88d3801da7fa83b9',
                    'weight' => 'bold',
                    'style' => 'normal',
                ],
            ],
        ],
    ];

    /**
     * The packages this install knows about.
     *
     * @return array<string, array<string, mixed>>
     */
    public function packages(): array
    {
        return self::FONT_PACKAGES;
    }

    /**
     * Whether a missing font may be fetched while the app runs
     * (PDF_FONTS_DOWNLOAD). An image that bakes its fonts turns it off.
     */
    public function downloadsEnabled(): bool
    {
        return (bool) config('pdf.fonts.download', true);
    }

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
     * Downloads synchronously if not already installed (blocking fallback),
     * unless downloads are off. A font that cannot be had is reported and
     * the document falls back to Noto Sans.
     */
    public function ensureFontsForLocale(string $locale): void
    {
        $package = $this->getPackageForLocale($locale);

        if (! $package || $this->isInstalled($package) || ! $this->downloadsEnabled()) {
            return;
        }

        try {
            $this->downloadPackage($package);
        } catch (RuntimeException $e) {
            report($e);
        }
    }

    /**
     * Resolve where a package's font files live on disk.
     * Bundled packages ship with the repo under resources/static/fonts/.
     * On-demand packages are read from the baked directory (PDF_FONTS_PATH)
     * when every file is there, and otherwise from storage/fonts/, where
     * downloads land. The baked directory has to sit under the application
     * directory, which is dompdf's chroot.
     */
    private function packageDir(array $package): string
    {
        if (! empty($package['bundled'])) {
            return resource_path('static/fonts');
        }

        $baked = rtrim((string) config('pdf.fonts.path'), '/');

        if ($baked !== '' && $this->hasAllFiles($baked, $package)) {
            return $baked;
        }

        return storage_path('fonts');
    }

    /**
     * Check if a font package is installed (all files present).
     */
    public function isInstalled(array $package): bool
    {
        return $this->hasAllFiles($this->packageDir($package), $package);
    }

    private function hasAllFiles(string $dir, array $package): bool
    {
        foreach ($package['files'] as $entry) {
            if (! File::exists($dir.'/'.$entry['file'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Download a font package into $directory, storage/fonts/ by default.
     * No-op for bundled packages — those ship with the repo. A file already
     * there is kept; a new one is kept only when its sha256 matches.
     *
     * @throws RuntimeException when a file cannot be fetched or does not match
     */
    public function downloadPackage(array $package, ?string $directory = null): void
    {
        if (! empty($package['bundled'])) {
            return;
        }

        $directory ??= storage_path('fonts');
        File::ensureDirectoryExists($directory, 0755);

        foreach ($package['files'] as $entry) {
            $targetPath = $directory.'/'.$entry['file'];

            if (File::exists($targetPath)) {
                continue;
            }

            $response = Http::timeout(180)->get($entry['url']);

            if (! $response->successful()) {
                throw new RuntimeException("Could not download {$entry['file']} (HTTP {$response->status()}).");
            }

            $body = $response->body();

            if (! hash_equals($entry['sha256'], hash('sha256', $body))) {
                throw new RuntimeException("{$entry['file']} does not match its checksum, so it was not installed.");
            }

            // Written aside and moved into place, so a reader never meets half a font.
            File::put($targetPath.'.part', $body);
            File::move($targetPath.'.part', $targetPath);
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
                'bundled' => ! empty($package['bundled']),
                'downloadable' => empty($package['bundled']) && $this->downloadsEnabled(),
            ];
        }

        return $statuses;
    }

    /**
     * Generate @font-face CSS rules for all installed on-demand fonts.
     * Used by the PDF fonts partial so dompdf can resolve CJK families
     * via standard CSS — no separate registerFont() dance required.
     */
    /**
     * Absolute paths of every installed font file, keyed by filename.
     *
     * getInstalledFontFaces() writes those absolute paths straight into
     * `src: url(...)`, which only works for a renderer sharing this filesystem.
     * Gotenberg runs Chromium in a separate container, so it needs the files
     * sent alongside the document — see GotenbergPdfDriver.
     *
     * @return array<string, string>
     */
    public function getInstalledFontFilePaths(): array
    {
        $paths = [];

        foreach (self::FONT_PACKAGES as $package) {
            if (! $this->isInstalled($package)) {
                continue;
            }

            $dir = $this->packageDir($package);

            foreach ($package['files'] as $entry) {
                $paths[$entry['file']] = $dir.'/'.$entry['file'];
            }
        }

        return $paths;
    }

    public function getInstalledFontFaces(): array
    {
        $faces = [];

        foreach (self::FONT_PACKAGES as $package) {
            if (! $this->isInstalled($package)) {
                continue;
            }

            $dir = $this->packageDir($package);

            foreach ($package['files'] as $entry) {
                $filePath = $dir.'/'.$entry['file'];

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
