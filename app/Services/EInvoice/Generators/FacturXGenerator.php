<?php

namespace App\Services\EInvoice\Generators;

use App\Models\Invoice;
use App\Services\EInvoice\Contracts\EInvoiceGeneratorInterface;
use App\Services\EInvoice\DataTransferObjects\InvoiceData;
use Sabre\Xml\Service;

class FacturXGenerator implements EInvoiceGeneratorInterface
{
    private Service $xmlService;

    public function __construct()
    {
        $this->xmlService = new Service;
        $this->xmlService->namespaceMap = [
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100' => '',
            'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100' => 'ram',
            'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100' => 'udt',
        ];
    }

    public function generate(Invoice $invoice, string $format = 'Factur-X'): array
    {
        $invoiceData = InvoiceData::fromInvoice($invoice);

        // Generate XML (similar to CII but with Factur-X specific elements)
        $xml = $this->generateFacturXXml($invoiceData);

        // Generate PDF using existing PDF service
        $pdf = $this->generateFacturXPDF($invoice);

        return [
            'xml' => $xml,
            'pdf' => $pdf,
        ];
    }

    public function getSupportedFormats(): array
    {
        return ['Factur-X', 'ZUGFeRD'];
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

        // Factur-X specific validations
        if (empty($invoice->company->vat_id) && empty($invoice->company->tax_id)) {
            $errors[] = 'Supplier VAT ID or Tax ID is required for Factur-X';
        }

        return $errors;
    }

    private function generateFacturXXml(InvoiceData $invoiceData): string
    {
        $xml = $this->xmlService->write('CrossIndustryInvoice', [
            'ExchangedDocumentContext' => [
                'GuidelineSpecifiedDocumentContextParameter' => [
                    'ID' => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic',
                ],
            ],
            'ExchangedDocument' => [
                'ID' => $invoiceData->number,
                'TypeCode' => '380',
                'IssueDateTime' => [
                    'DateTimeString' => [
                        '_' => $invoiceData->issueDate.'T00:00:00',
                        'format' => '102',
                    ],
                ],
                'IncludedNote' => $invoiceData->notes ? [
                    'Content' => $invoiceData->notes,
                ] : null,
            ],
            'SupplyChainTradeTransaction' => [
                'IncludedSupplyChainTradeLineItem' => $this->buildSupplyChainTradeLineItems($invoiceData->lineItems, $invoiceData->currency),
                'ApplicableHeaderTradeAgreement' => [
                    'SellerTradeParty' => $this->buildTradeParty($invoiceData->supplier),
                    'BuyerTradeParty' => $this->buildTradeParty($invoiceData->customer),
                ],
                'ApplicableHeaderTradeDelivery' => [
                    'ActualDeliverySupplyChainEvent' => $invoiceData->deliveryDate ? [
                        'OccurrenceDateTime' => [
                            'DateTimeString' => [
                                '_' => $invoiceData->deliveryDate.'T00:00:00',
                                'format' => '102',
                            ],
                        ],
                    ] : null,
                ],
                'ApplicableHeaderTradeSettlement' => [
                    'InvoiceCurrencyCode' => $invoiceData->currency,
                    'SpecifiedTradeSettlementPaymentMeans' => [
                        'TypeCode' => '30', // Credit transfer
                        'Information' => $invoiceData->paymentTerms,
                    ],
                    'ApplicableTradeTax' => $this->buildTradeTaxes($invoiceData->taxes, $invoiceData->currency),
                    'SpecifiedTradeSettlementHeaderMonetarySummation' => [
                        'LineTotalAmount' => [
                            '_' => number_format($invoiceData->netAmount, 2, '.', ''),
                            'currencyID' => $invoiceData->currency,
                        ],
                        'TaxBasisTotalAmount' => [
                            '_' => number_format($invoiceData->netAmount, 2, '.', ''),
                            'currencyID' => $invoiceData->currency,
                        ],
                        'TaxTotalAmount' => [
                            '_' => number_format($invoiceData->taxAmount, 2, '.', ''),
                            'currencyID' => $invoiceData->currency,
                        ],
                        'GrandTotalAmount' => [
                            '_' => number_format($invoiceData->totalAmount, 2, '.', ''),
                            'currencyID' => $invoiceData->currency,
                        ],
                    ],
                ],
            ],
        ]);

        return $xml;
    }

    private function generateFacturXPDF(Invoice $invoice): string
    {
        // Use existing PDF generation but ensure it's Factur-X compatible
        $pdf = $invoice->getPDFData();

        // For Factur-X, we need to embed the XML in the PDF
        // This is a simplified version - in production you'd use a proper PDF library
        // that supports embedding XML attachments

        return $pdf->output();
    }

    private function buildTradeParty($party): array
    {
        $partyData = [
            'Name' => $party->name,
        ];

        $address = $party->address ?? $party->billingAddress;
        if ($address) {
            $partyData['PostalTradeAddress'] = [
                'PostcodeCode' => $address->postalCode,
                'LineOne' => $address->street,
                'CityName' => $address->city,
                'CountryID' => $address->countryCode,
            ];
        }

        if ($party->vatId) {
            $partyData['SpecifiedTaxRegistration'] = [
                'ID' => [
                    '_' => $party->vatId,
                    'schemeID' => 'VA',
                ],
            ];
        }

        return $partyData;
    }

    private function buildTradeTaxes(array $taxes, string $currency): array
    {
        $tradeTaxes = [];

        foreach ($taxes as $tax) {
            $tradeTaxes[] = [
                'CalculatedAmount' => [
                    '_' => number_format($tax->amount, 2, '.', ''),
                    'currencyID' => $currency,
                ],
                'TypeCode' => 'VAT',
                'BasisAmount' => [
                    '_' => number_format($tax->baseAmount, 2, '.', ''),
                    'currencyID' => $currency,
                ],
                'CategoryCode' => 'S',
                'RateApplicablePercent' => number_format($tax->rate, 2, '.', ''),
            ];
        }

        return $tradeTaxes;
    }

    private function buildSupplyChainTradeLineItems(array $lineItems, string $currency): array
    {
        $items = [];

        foreach ($lineItems as $index => $item) {
            $items[] = [
                'AssociatedDocumentLineDocument' => [
                    'LineID' => (string) ($index + 1),
                ],
                'SpecifiedTradeProduct' => [
                    'Name' => $item->name,
                    'Description' => $item->description ?? $item->name,
                ],
                'SpecifiedLineTradeAgreement' => [
                    'NetPriceProductTradePrice' => [
                        'ChargeAmount' => [
                            '_' => number_format($item->unitPrice, 2, '.', ''),
                            'currencyID' => $currency,
                        ],
                        'BasisQuantity' => [
                            '_' => number_format($item->quantity, 2, '.', ''),
                            'unitCode' => $item->unit,
                        ],
                    ],
                ],
                'SpecifiedLineTradeDelivery' => [
                    'BilledQuantity' => [
                        '_' => number_format($item->quantity, 2, '.', ''),
                        'unitCode' => $item->unit,
                    ],
                ],
                'SpecifiedLineTradeSettlement' => [
                    'ApplicableTradeTax' => [
                        'TypeCode' => 'VAT',
                        'CategoryCode' => 'S',
                        'RateApplicablePercent' => number_format($item->taxRate, 2, '.', ''),
                    ],
                    'SpecifiedTradeSettlementLineMonetarySummation' => [
                        'LineTotalAmount' => [
                            '_' => number_format($item->netAmount, 2, '.', ''),
                            'currencyID' => $currency,
                        ],
                    ],
                ],
            ];
        }

        return $items;
    }
}
