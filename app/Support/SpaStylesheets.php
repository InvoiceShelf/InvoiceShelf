<?php

namespace App\Support;

/**
 * The SPA's stylesheet, for the few server-rendered pages that must look like
 * the SPA (the MCP consent screen, the mobile sign-in confirmation), so they
 * share its theme tokens.
 *
 * Taken from the Vite manifest entry of the SPA rather than added as an entry
 * of its own: Tailwind already scans resources/views, so every class used in
 * those pages is in that bundle.
 */
final class SpaStylesheets
{
    /**
     * @return list<string>
     */
    public static function urls(): array
    {
        $hot = public_path('hot');

        if (is_file($hot)) {
            return [rtrim((string) file_get_contents($hot)).'/resources/css/invoiceshelf.css'];
        }

        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($manifest), true);

        return array_values(array_map(
            fn (string $file): string => asset('build/'.$file),
            $entries['resources/scripts/main.ts']['css'] ?? [],
        ));
    }
}
