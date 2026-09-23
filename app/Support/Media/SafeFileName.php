<?php

namespace App\Support\Media;

use Illuminate\Support\Str;

/**
 * The name an upload is stored under, made from the name the client sent.
 *
 * Only the last extension survives, lowercased, and the rest becomes a slug
 * without dots. Uploads are served from the public disk under this name, and
 * a name such as `shell.php.jpg` would otherwise reach a web server that runs
 * any file with `.php` anywhere in its name.
 */
final class SafeFileName
{
    public static function from(string $name): string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(str_replace('.', ' ', (string) pathinfo($name, PATHINFO_FILENAME)));

        if ($base === '') {
            $base = 'file';
        }

        $extension = preg_replace('/[^a-z0-9]/', '', $extension);

        return $extension === '' ? $base : $base.'.'.$extension;
    }
}
