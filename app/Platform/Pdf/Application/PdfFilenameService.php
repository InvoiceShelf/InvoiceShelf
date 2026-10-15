<?php

namespace App\Platform\Pdf\Application;

use App\Domains\Accounts\Models\CompanySetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PdfFilenameService
{
    public function filename(Model $document, string $collection): string
    {
        $number = (string) $document->{$collection.'_number'};
        $format = CompanySetting::getSetting('pdf_filename_format', $document->company_id);

        if (! is_string($format) || trim($format) === '') {
            return $this->sanitize($number).'.pdf';
        }

        $fields = array_map(
            fn ($value) => html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $document->getFieldsArray()
        );
        $fields['{DOCUMENT_NUMBER}'] = $number;
        $date = $document->getAttribute($collection.'_date');
        $fields['{DOCUMENT_DATE}'] = $date ? Carbon::parse($date)->toDateString() : '';
        $name = strtr($format, $fields);
        $name = preg_replace('/\{[^{}]*\}/u', '', $name);
        $name = preg_replace('/(?:\s+-\s*){2,}/u', ' - ', $name);
        $name = $this->sanitize($name);

        return ($name === 'document' ? $this->sanitize($number) : $name).'.pdf';
    }

    public function disposition(string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($filename));
        $fallback = str_replace('%', '', $fallback);

        return HeaderUtils::makeDisposition('inline', $filename, $fallback ?: 'document.pdf');
    }

    private function sanitize(string $name): string
    {
        $name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        $name = preg_replace('/\.pdf$/i', '', trim($name));
        $name = trim($name, " .-\t\n\r\0\x0B");
        $name = mb_strcut($name, 0, 180, 'UTF-8');

        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $name)) {
            $name = '_'.$name;
        }

        return rtrim($name, ' .') ?: 'document';
    }
}
