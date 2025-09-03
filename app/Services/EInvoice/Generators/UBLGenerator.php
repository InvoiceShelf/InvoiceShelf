<?php

namespace App\Services\EInvoice\Generators;

use App\Models\Invoice;
use App\Services\EInvoice\Contracts\EInvoiceGeneratorInterface;
use App\Services\EInvoice\DataTransferObjects\InvoiceData;
use Sabre\Xml\Service;

class UBLGenerator implements EInvoiceGeneratorInterface
{
    private Service $xmlService;

    public function __construct()
    {
        $this->xmlService = new Service;
        $this->xmlService->namespaceMap = [
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' => '',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2' => 'cac',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2' => 'cbc',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2' => 'ext',
        ];
    }

    public function generate(Invoice $invoice, string $format = 'UBL'): array
    {
        $invoiceData = InvoiceData::fromInvoice($invoice);

        $xml = $this->xmlService->write('Invoice', [
            'cbc:ID' => $invoiceData->number,
            'cbc:IssueDate' => $invoiceData->issueDate,
            'cbc:DueDate' => $invoiceData->dueDate,
            'cbc:InvoiceTypeCode' => '380', // Commercial Invoice
            'cbc:DocumentCurrencyCode' => $invoiceData->currency,
            'cbc:LineCountNumeric' => count($invoiceData->lineItems),

            // Supplier
            'cac:AccountingSupplierParty' => [
                'cac:Party' => $this->buildParty($invoiceData->supplier),
            ],

            // Customer
            'cac:AccountingCustomerParty' => [
                'cac:Party' => $this->buildParty($invoiceData->customer),
            ],

            // Tax Total
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_' => number_format($invoiceData->taxAmount, 2, '.', ''),
                    'currencyID' => $invoiceData->currency,
                ],
                'cac:TaxSubtotal' => $this->buildTaxSubtotals($invoiceData->taxes, $invoiceData->currency),
            ],

            // Legal Monetary Total
            'cac:LegalMonetaryTotal' => [
                'cbc:LineExtensionAmount' => [
                    '_' => number_format($invoiceData->netAmount, 2, '.', ''),
                    'currencyID' => $invoiceData->currency,
                ],
                'cbc:TaxExclusiveAmount' => [
                    '_' => number_format($invoiceData->netAmount, 2, '.', ''),
                    'currencyID' => $invoiceData->currency,
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_' => number_format($invoiceData->totalAmount, 2, '.', ''),
                    'currencyID' => $invoiceData->currency,
                ],
                'cbc:PayableAmount' => [
                    '_' => number_format($invoiceData->totalAmount, 2, '.', ''),
                    'currencyID' => $invoiceData->currency,
                ],
            ],

            // Invoice Lines
            'cac:InvoiceLine' => $this->buildInvoiceLines($invoiceData->lineItems, $invoiceData->currency),
        ]);

        return [
            'xml' => $xml,
            'pdf' => null, // UBL is XML only
        ];
    }

    public function getSupportedFormats(): array
    {
        return ['UBL'];
    }

    public function validate(Invoice $invoice): array
    {
        $errors = [];

        // Basic validation
        if (empty($invoice->invoice_number)) {
            $errors[] = 'Invoice number is required';
        }

        if (empty($invoice->company->name)) {
            $errors[] = 'Supplier name is required';
        }

        if (empty($invoice->customer->name)) {
            $errors[] = 'Customer name is required';
        }

        if ($invoice->items->isEmpty()) {
            $errors[] = 'At least one line item is required';
        }

        return $errors;
    }

    private function buildParty($party): array
    {
        $partyData = [
            'cac:PartyIdentification' => [
                'cbc:ID' => $party->vatId ?? $party->taxId ?? $party->name,
            ],
            'cac:PartyName' => [
                'cbc:Name' => $party->name,
            ],
        ];

        $address = $party->address ?? $party->billingAddress;
        if ($address) {
            $partyData['cac:PostalAddress'] = [
                'cbc:StreetName' => $address->street,
                'cbc:CityName' => $address->city,
                'cbc:PostalZone' => $address->postalCode,
                'cac:Country' => [
                    'cbc:IdentificationCode' => $address->countryCode,
                ],
            ];
        }

        if ($party->vatId) {
            $partyData['cac:PartyTaxScheme'] = [
                'cbc:CompanyID' => $party->vatId,
                'cac:TaxScheme' => [
                    'cbc:ID' => 'VAT',
                ],
            ];
        }

        return $partyData;
    }

    private function buildTaxSubtotals(array $taxes, string $currency): array
    {
        $subtotals = [];

        foreach ($taxes as $tax) {
            $subtotals[] = [
                'cbc:TaxableAmount' => [
                    '_' => number_format($tax->baseAmount, 2, '.', ''),
                    'currencyID' => $currency,
                ],
                'cbc:TaxAmount' => [
                    '_' => number_format($tax->amount, 2, '.', ''),
                    'currencyID' => $currency,
                ],
                'cac:TaxCategory' => [
                    'cbc:ID' => 'S', // Standard rate
                    'cbc:Percent' => number_format($tax->rate, 2, '.', ''),
                    'cac:TaxScheme' => [
                        'cbc:ID' => 'VAT',
                    ],
                ],
            ];
        }

        return $subtotals;
    }

    private function buildInvoiceLines(array $lineItems, string $currency): array
    {
        $lines = [];

        foreach ($lineItems as $index => $item) {
            $lines[] = [
                'cbc:ID' => (string) ($index + 1),
                'cbc:InvoicedQuantity' => [
                    '_' => number_format($item->quantity, 2, '.', ''),
                    'unitCode' => $item->unit,
                ],
                'cbc:LineExtensionAmount' => [
                    '_' => number_format($item->netAmount, 2, '.', ''),
                    'currencyID' => $currency,
                ],
                'cac:Item' => [
                    'cbc:Description' => $item->description ?? $item->name,
                    'cbc:Name' => $item->name,
                ],
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_' => number_format($item->unitPrice, 2, '.', ''),
                        'currencyID' => $currency,
                    ],
                ],
            ];
        }

        return $lines;
    }
}
