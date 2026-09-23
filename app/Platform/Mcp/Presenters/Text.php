<?php

namespace App\Platform\Mcp\Presenters;

/**
 * Rich text from the editors (notes, descriptions) as plain text.
 */
final class Text
{
    public static function plain(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = preg_replace('/<(br|\/p|\/li|\/h[1-6])\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
