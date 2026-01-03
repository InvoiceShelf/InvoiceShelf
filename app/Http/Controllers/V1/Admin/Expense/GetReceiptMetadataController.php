<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use thiagoalessio\TesseractOCR\TesseractOCR;

class GetReceiptMetadataController extends Controller
{
    /**
     * Retrieve metadata of an expense receipt.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Expense $expense)
    {
        $this->authorize('view', $expense);

        if ($expense) {
            $media = $expense->getFirstMedia('receipts');

            if ($media) {
                $path = $media->getPath();
                $mime = $media->mime_type;
                $text = '';

                if (str_starts_with($mime, 'image/')) {
                    try {
                        $text = (new TesseractOCR($path))->run();
                    } catch (\Exception $e) {
                        $text = 'Error during OCR: '.$e->getMessage();
                    }
                } elseif ($mime === 'application/pdf') {
                    try {
                        $text = shell_exec('pdftotext -layout '.escapeshellarg($path).' -');
                    } catch (\Exception $e) {
                        $text = 'Error reading PDF: '.$e->getMessage();
                    }
                }

                $lines = explode("\n", $text);
                $extractedDates = $this->extractDatesFromLines($lines);
                $extractedCurrencies = $this->extractCurrenciesFromLines($lines);
                $extractedAmounts = $this->extractAmountsFromLines($lines);
                $extractedCompany = $this->extractCompanyFromLines($lines, $expense->company_id);
                $processedLines = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    if (str_contains($line, ':')) {
                        $parts = explode(':', $line, 2);
                        $label = trim($parts[0]);
                        $value = trim($parts[1]);

                        // Check if label contains at least one letter to avoid splitting times like 12:30
                        if (preg_match('/[a-zA-Z]/', $label)) {
                            $processedLines[] = [
                                'label' => $label,
                                'value' => $value,
                            ];
                        } else {
                            $processedLines[] = $line;
                        }
                    } else {
                        $processedLines[] = $line;
                    }
                }

                return response()->json([
                    'media' => $media,
                    'extracted_rows' => $processedLines,
                    'suggested_fields' => [
                        'dates' => $extractedDates,
                        'currencies' => $extractedCurrencies,
                        'amounts' => $extractedAmounts,
                        'company' => $extractedCompany,
                    ],
                ]);
            }

            return response()->json(['message' => 'Receipt does not exist.'], 404);
        }
    }

    private function extractDatesFromLines(array $lines)
    {
        $datePatterns = [
            '/\b\d{4}-\d{2}-\d{2}\b/', // YYYY-MM-DD
            '/\b\d{2}\/\d{2}\/\d{4}\b/', // DD/MM/YYYY or MM/DD/YYYY
            '/\b\d{2}\.\d{2}\.\d{4}\b/', // DD.MM.YYYY
            '/\b\d{2}-\d{2}-\d{4}\b/', // DD-MM-YYYY
            '/\b\d{1,2}\s(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s\d{4}\b/i', // 12 Jan 2023
            '/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s\d{1,2},\s\d{4}\b/i', // May 1, 2025
        ];

        $foundDates = [];

        foreach ($lines as $line) {
            foreach ($datePatterns as $pattern) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches[0] as $match) {
                        $foundDates[] = $match;
                    }
                }
            }
        }

        $uniqueDates = [];
        $seenTimestamps = [];

        foreach ($foundDates as $date) {
            $timestamp = strtotime($date);

            if ($timestamp) {
                if (! in_array($timestamp, $seenTimestamps)) {
                    $seenTimestamps[] = $timestamp;
                    $uniqueDates[] = $date;
                }
            }
        }

        return $uniqueDates;
    }

    private function extractCurrenciesFromLines(array $lines)
    {
        $currencyPatterns = [
            '/[$€£¥₹]/', // Symbols
            '/\b(USD|EUR|GBP|JPY|CAD|AUD|INR|CHF|CNY|SEK|NZD)\b/', // Common ISO codes
        ];

        $foundCurrencies = [];

        foreach ($lines as $line) {
            foreach ($currencyPatterns as $pattern) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches[0] as $match) {
                        $foundCurrencies[] = $match;
                    }
                }
            }
        }

        return array_values(array_unique($foundCurrencies));
    }

    private function extractAmountsFromLines(array $lines)
    {
        $amounts = [];

        foreach ($lines as $line) {
            if (preg_match_all('/[\d,.]+/', $line, $matches)) {
                foreach ($matches[0] as $raw) {
                    // Must contain at least one digit
                    if (! preg_match('/\d/', $raw)) {
                        continue;
                    }

                    // Check if it looks like a price with 2 decimals (e.g. 12.34 or 12,34)
                    if (strlen($raw) >= 3) {
                        $separator = substr($raw, -3, 1);
                        if (in_array($separator, ['.', ','])) {
                            $clean = $raw;

                            // Normalize based on separators
                            if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
                                if (strrpos($clean, '.') > strrpos($clean, ',')) {
                                    // 1,234.56
                                    $clean = str_replace(',', '', $clean);
                                } else {
                                    // 1.234,56
                                    $clean = str_replace('.', '', $clean);
                                    $clean = str_replace(',', '.', $clean);
                                }
                            } else {
                                // Only one separator type
                                $clean = str_replace(',', '.', $clean);
                            }

                            if (is_numeric($clean)) {
                                $amounts[] = (float) $clean;
                            }
                        }
                    }
                }
            }
        }

        $amounts = array_unique($amounts);
        rsort($amounts);

        return array_values($amounts);
    }

    private function extractCompanyFromLines(array $lines, $companyId)
    {
        $customers = Customer::where('company_id', $companyId)->pluck('name')->toArray();

        $bestMatch = null;
        $highestSimilarity = 0;
        $threshold = 70;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_contains($line, ':')) {
                continue;
            }

            foreach ($customers as $customer) {
                similar_text(strtolower($line), strtolower($customer), $percent);

                if ($percent > $highestSimilarity && $percent >= $threshold) {
                    $highestSimilarity = $percent;
                    $bestMatch = $customer;
                }
            }
        }

        return $bestMatch;
    }
}
