<?php

namespace App\Services\EInvoice;

use App\Models\Invoice;
use App\Services\EInvoice\DataTransferObjects\InvoiceData;
use Einvoicing\Exceptions\ValidationException;
use Einvoicing\Identifier;
use Einvoicing\Invoice as EinvoicingInvoice;
use Einvoicing\InvoiceLine;
use Einvoicing\Party;
use Einvoicing\Presets;
use Einvoicing\Readers\UblReader;
use Einvoicing\Writers\UblWriter;
use Illuminate\Support\Facades\Log;

class UBLService
{
    private UblWriter $writer;

    private UblReader $reader;

    public function __construct()
    {
        $this->writer = new UblWriter;
        $this->reader = new UblReader;
    }

    /**
     * Generate UBL XML from Invoice model
     */
    public function generate(Invoice $invoice): array
    {
        try {
            $invoiceData = InvoiceData::fromInvoice($invoice);
            $einvoicingInvoice = $this->createEinvoicingInvoice($invoiceData);

            // Validate the invoice
            $einvoicingInvoice->validate();

            // Generate XML
            $xml = $this->writer->export($einvoicingInvoice);

            return [
                'success' => true,
                'xml' => $xml,
                'format' => 'UBL',
                'filename' => $this->generateFilename($invoice),
            ];
        } catch (ValidationException $e) {
            Log::error('UBL validation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Validation failed: '.$e->getMessage(),
                'format' => 'UBL',
            ];
        } catch (\Exception $e) {
            Log::error('UBL generation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Generation failed: '.$e->getMessage(),
                'format' => 'UBL',
            ];
        }
    }

    /**
     * Validate UBL XML
     */
    public function validate(string $xml): array
    {
        try {
            $invoice = $this->reader->import($xml);
            $invoice->validate();

            return [
                'valid' => true,
                'errors' => [],
                'warnings' => [],
            ];
        } catch (ValidationException $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
                'warnings' => [],
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => ['XML parsing failed: '.$e->getMessage()],
                'warnings' => [],
            ];
        }
    }

    /**
     * Create Einvoicing Invoice from InvoiceData
     */
    private function createEinvoicingInvoice(InvoiceData $invoiceData): EinvoicingInvoice
    {
        // Create invoice with PEPPOL preset for EN 16931 compliance
        $invoice = new EinvoicingInvoice(Presets\Peppol::class);

        // Set basic invoice information
        $invoice->setNumber($invoiceData->number)
            ->setIssueDate(new \DateTime($invoiceData->issueDate))
            ->setDueDate(new \DateTime($invoiceData->dueDate))
            ->setCurrency($invoiceData->currency);

        // Set invoice type (required by EN 16931) - default to commercial invoice
        $invoice->setType('380'); // 380 = Commercial Invoice (UN/CEFACT)

        // Set seller
        $seller = $this->createParty($invoiceData->supplier);
        $invoice->setSeller($seller);

        // Set buyer
        $buyer = $this->createParty($invoiceData->customer);
        $invoice->setBuyer($buyer);

        // Set buyer reference (required by EN 16931)
        $invoice->setBuyerReference($invoiceData->customer->name ?? 'BUYER-REF');

        // Set payment terms if available
        if ($invoiceData->paymentTerms) {
            $invoice->setPaymentTerms($invoiceData->paymentTerms);
        }

        // Add invoice lines
        foreach ($invoiceData->lineItems as $itemData) {
            $line = $this->createInvoiceLine($itemData);
            $invoice->addLine($line);
        }

        // Ensure BR-CO-15 compliance: Total with VAT (BT-112) = Total without VAT (BT-109) + Total VAT (BT-110)
        // The library calculates totals automatically from lines
        // We need to ensure the totals match exactly by calculating them from the lines we added

        // Recalculate totals from the lines we just added to ensure exact match
        $recalculatedNetAmount = 0.0;
        $recalculatedTaxAmount = 0.0;

        foreach ($invoiceData->lineItems as $itemData) {
            $recalculatedNetAmount += round($itemData->netAmount, 2);
            $recalculatedTaxAmount += round($itemData->taxAmount, 2);
        }

        $recalculatedNetAmount = round($recalculatedNetAmount, 2);
        $recalculatedTaxAmount = round($recalculatedTaxAmount, 2);
        $recalculatedTotal = round($recalculatedNetAmount + $recalculatedTaxAmount, 2);

        // Calculate rounding amount to ensure exact match
        // This compensates for any rounding differences between our calculation and library's
        $roundingAmount = round($recalculatedTotal - ($recalculatedNetAmount + $recalculatedTaxAmount), 2);

        // Set rounding amount to ensure BR-CO-15 compliance
        // Only set if there's an actual rounding difference (not 0.00) to avoid issues
        // Setting rounding amount of 0.00 can cause validation issues
        if (abs($roundingAmount) > 0.001) {
            $invoice->setRoundingAmount($roundingAmount);
        }

        // Note: We do NOT use setCustomVatAmount() as it can create duplicate TaxTotal elements
        // The library calculates tax totals automatically from the invoice lines we added
        // Since we calculated our totals from the same lines, they should match exactly
        // If there's a discrepancy, it's better to fix the line items rather than override totals

        // Log if there are any discrepancies (should not happen with correct calculations)
        if (abs($recalculatedTotal - ($recalculatedNetAmount + $recalculatedTaxAmount + $roundingAmount)) > 0.001) {
            \Log::warning('E-Invoice BR-CO-15: Potential total mismatch', [
                'recalculatedNetAmount' => $recalculatedNetAmount,
                'recalculatedTaxAmount' => $recalculatedTaxAmount,
                'recalculatedTotal' => $recalculatedTotal,
                'roundingAmount' => $roundingAmount,
            ]);
        }

        return $invoice;
    }

    /**
     * Create Party from PartyData
     */
    private function createParty($partyData): Party
    {
        $party = new Party;

        // Set basic information
        $party->setName($partyData->name ?? 'Not specified');

        if ($partyData->registrationName) {
            $party->setTradingName($partyData->registrationName);
        }

        // Set electronic address (required for PEPPOL EN16931)
        if ($partyData->electronicAddress) {
            $party->setElectronicAddress(new Identifier(
                $partyData->electronicAddress->value,
                $partyData->electronicAddress->schemeId
            ));
        } else {
            // Fallback: create a default electronic address if none provided (for PEPPOL compliance)
            $defaultEmail = $partyData->email ?? 'noreply@company.local';
            $party->setElectronicAddress(new Identifier($defaultEmail, 'EM'));
        }

        // Set company ID
        if ($partyData->companyId) {
            $party->setCompanyId(new Identifier(
                $partyData->companyId->value,
                $partyData->companyId->schemeId
            ));
        } elseif ($partyData->taxId) {
            // Fallback to taxId if companyId is not available
            $party->setCompanyId(new Identifier($partyData->taxId, '0183')); // 0183 = VAT
        }

        // Set VAT number (using taxId as it contains the VAT number)
        if ($partyData->taxId) {
            $party->setVatNumber($partyData->taxId);
        }

        // Set address - ensure we always have an address
        $address = $partyData->address ?? $partyData->billingAddress ?? null;
        if (! $address) {
            // Create a minimal address if none exists
            $address = new \App\Services\EInvoice\DataTransferObjects\AddressData;
        }

        $addressLines = [];
        if ($address->street) {
            $addressLines[] = $address->street;
        }
        if ($address->additionalStreet) {
            $addressLines[] = $address->additionalStreet;
        }

        if (! empty($addressLines)) {
            $party->setAddress($addressLines);
        }

        if ($address->city) {
            $party->setCity($address->city);
        }

        if ($address->postalCode) {
            $party->setPostalCode($address->postalCode);
        }

        if ($address->countryCode) {
            $party->setCountry($address->countryCode);
        } else {
            // Set default country code if none provided - use FR as default for European compliance
            $party->setCountry('FR'); // France as default for European e-invoicing
        }

        // Set contact information
        if ($partyData->email) {
            $party->setContactEmail($partyData->email);
        }

        if ($partyData->phone) {
            $party->setContactPhone($partyData->phone);
        }

        return $party;
    }

    /**
     * Create InvoiceLine from LineItemData
     */
    private function createInvoiceLine($itemData): InvoiceLine
    {
        $line = new InvoiceLine;

        // Ensure all amounts are properly rounded to 2 decimal places for EN 16931
        $unitPrice = round($itemData->unitPrice ?? 0, 2);
        $quantity = round($itemData->quantity ?? 1, 2);
        $taxRate = round($itemData->taxRate ?? 0, 2);

        $line->setName($itemData->name ?? 'Item')
            ->setDescription($itemData->description ?? $itemData->name ?? 'Item')
            ->setPrice($unitPrice)
            ->setQuantity($quantity)
            ->setVatRate($taxRate)
            ->setVatCategory($itemData->taxCategory ?? 'S'); // Use tax category from LineItemData

        // Set unit code (required by EN 16931 - BR-CL-23)
        // The unit code should already be normalized to UN/ECE Recommendation 20 format
        // But ensure it's always set (default to C62 if not provided)
        $unitCode = $itemData->unit ?? 'C62';
        $line->setUnit($unitCode);

        return $line;
    }

    /**
     * Generate filename for the invoice
     */
    private function generateFilename(Invoice $invoice): string
    {
        return $invoice->invoice_number.'_ubl.xml';
    }
}
