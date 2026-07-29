<?php

namespace App\Support\Pdf;

use App\Models\Company;

/**
 * Document properties written into the PDF itself.
 *
 * Generated files used to carry none, so an archive of them showed a column of
 * blank titles and no author. It also matters for PDF/A, whose whole point is
 * being readable years later by someone who has only the file.
 *
 * Both drivers take the same key names: dompdf via addInfo(), Gotenberg via
 * metadata().
 */
final class PdfMetadata
{
    /**
     * @return array<string, string>
     */
    public static function forDocument(string $subject, ?string $number, ?Company $company): array
    {
        $title = trim($subject.' '.($number ?? ''));

        return array_filter([
            'Title' => $title,
            'Subject' => $subject,
            'Author' => $company?->name,
            'Creator' => config('app.name', 'InvoiceShelf'),
        ], fn ($value) => is_string($value) && $value !== '');
    }
}
