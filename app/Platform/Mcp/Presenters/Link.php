<?php

namespace App\Platform\Mcp\Presenters;

/**
 * Where a record opens in the app, so an assistant can hand the user a link.
 */
final class Link
{
    public static function to(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/'.ltrim($path, '/');
    }
}
