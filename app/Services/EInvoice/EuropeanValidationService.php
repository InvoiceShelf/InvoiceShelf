<?php

namespace App\Services\EInvoice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EuropeanValidationService
{
    /**
     * Validate e-invoice XML against European validation service
     *
     * @param  string  $xml  The XML content to validate
     * @param  string  $format  The format (UBL, CII, etc.)
     * @return array Validation result with 'valid', 'errors', and 'warnings'
     */
    public function validate(string $xml, string $format = 'UBL'): array
    {
        try {
            $response = Http::timeout(30)->post('https://www.itb.ec.europa.eu/vitb/rest/validate', [
                'xml' => $xml,
                'format' => $this->mapFormatToApi($format),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'valid' => $data['valid'] ?? false,
                    'errors' => $data['errors'] ?? [],
                    'warnings' => $data['warnings'] ?? [],
                    'source' => 'european_api',
                ];
            }

            return [
                'valid' => false,
                'errors' => ['European validation service returned status: '.$response->status()],
                'warnings' => [],
                'source' => 'european_api',
            ];

        } catch (\Exception $e) {
            Log::warning('European validation service unavailable', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'errors' => ['Could not connect to European validation service: '.$e->getMessage()],
                'warnings' => [],
                'source' => 'european_api',
            ];
        }
    }

    /**
     * Map format to API format string
     */
    private function mapFormatToApi(string $format): string
    {
        return match ($format) {
            'UBL' => 'UBL Invoice XML - release 1.3.14.2',
            default => 'UBL Invoice XML - release 1.3.14.2',
        };
    }
}
