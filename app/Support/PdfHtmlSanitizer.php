<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class PdfHtmlSanitizer
{
    /**
     * Sanitize HTML that will be rendered inside Dompdf. Removes tags that can trigger
     * network requests (SSRF) or carry executable handlers, while keeping common
     * text-formatting markup used in invoice/estimate/payment notes.
     */
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Legacy/invalid `</br>` is dropped by libxml and collapses lines in PDF output; normalize to `<br />`.
        $html = str_replace('</br>', '<br />', $html);

        $allowedTags = '<br><br/><p><b><strong><i><em><u><ol><ul><li><table><tr><td><th><thead><tbody><tfoot><h1><h2><h3><h4><blockquote>';
        $html = strip_tags($html, $allowedTags);

        $previous = libxml_use_internal_errors(true);

        $doc = new DOMDocument;
        $wrapped = '<?xml encoding="UTF-8"?><div id="__pdf_notes">'.$html.'</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $root = $xpath->query('//*[@id="__pdf_notes"]')->item(0);

        if (! $root instanceof DOMElement) {
            return $html;
        }

        foreach ($xpath->query('.//*', $root) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $toRemove = [];

            foreach ($element->attributes as $attr) {
                if (self::shouldRemoveAttribute($attr->name)) {
                    $toRemove[] = $attr->name;
                }
            }

            foreach ($toRemove as $name) {
                $element->removeAttribute($name);
            }
        }

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    private static function shouldRemoveAttribute(string $name): bool
    {
        $lower = strtolower($name);

        if (str_starts_with($lower, 'on')) {
            return true;
        }

        return in_array($lower, [
            'style',
            'src',
            'href',
            'srcset',
            'srcdoc',
            'poster',
            'formaction',
            'xlink:href',
        ], true);
    }
}
