<?php

namespace App\Rules;

use App\Support\Pdf\PdfTemplateUtils;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The template must be one the picker actually offers.
 *
 * template_name was validated only as `required`, so any string was accepted and
 * stored. findFormattedTemplate() returns null for an unknown name, the null is
 * read as "not custom", and rendering falls through to `app.pdf.{type}.{name}` —
 * a raw "view not found" 500 at PDF time, long after the save that caused it.
 */
class PdfTemplateExists implements ValidationRule
{
    public function __construct(private readonly string $templateType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // The image is not needed to answer this, and building base64 previews
        // for every template just to validate a name would be wasteful.
        $names = array_column(PdfTemplateUtils::getFormattedTemplates($this->templateType, ''), 'name');

        if (! in_array($value, $names, true)) {
            $fail("The selected :attribute is not an available {$this->templateType} template.");
        }
    }
}
