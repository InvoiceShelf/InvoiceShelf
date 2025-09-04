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

        // Set seller
        $seller = $this->createParty($invoiceData->supplier);
        $invoice->setSeller($seller);

        // Set buyer
        $buyer = $this->createParty($invoiceData->customer);
        $invoice->setBuyer($buyer);

        // Set buyer reference (required by EN 16931)
        $invoice->setBuyerReference($invoiceData->customer->name ?? 'BUYER-REF');

        // Add invoice lines
        foreach ($invoiceData->lineItems as $itemData) {
            $line = $this->createInvoiceLine($itemData);
            $invoice->addLine($line);
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

        $line->setName($itemData->name ?? 'Item')
            ->setDescription($itemData->description ?? $itemData->name ?? 'Item')
            ->setPrice($itemData->unitPrice ?? 0)
            ->setQuantity($itemData->quantity ?? 1)
            ->setVatRate($itemData->taxRate ?? 0)
            ->setVatCategory($itemData->taxCategory ?? 'S'); // Use tax category from LineItemData

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
