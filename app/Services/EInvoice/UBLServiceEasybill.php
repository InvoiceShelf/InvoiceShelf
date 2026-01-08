<?php

namespace App\Services\EInvoice;

use App\Models\Invoice;
use App\Services\EInvoice\DataTransferObjects\InvoiceData;
use easybill\eInvoicing\Enums\CountryCode;
use easybill\eInvoicing\Enums\CurrencyCode;
use easybill\eInvoicing\Enums\DocumentType;
use easybill\eInvoicing\Enums\ElectronicAddressScheme;
use easybill\eInvoicing\Enums\UnitCode;
use easybill\eInvoicing\Transformer;
use easybill\eInvoicing\UBL\Documents\UblInvoice;
use easybill\eInvoicing\UBL\Models\AccountingParty;
use easybill\eInvoicing\UBL\Models\Address;
use easybill\eInvoicing\UBL\Models\Amount;
use easybill\eInvoicing\UBL\Models\Country;
use easybill\eInvoicing\UBL\Models\EndpointId;
use easybill\eInvoicing\UBL\Models\Id;
use easybill\eInvoicing\UBL\Models\InvoiceLine;
use easybill\eInvoicing\UBL\Models\Item;
use easybill\eInvoicing\UBL\Models\LegalMonetaryTotal;
use easybill\eInvoicing\UBL\Models\Party;
use easybill\eInvoicing\UBL\Models\PartyLegalEntity;
use easybill\eInvoicing\UBL\Models\PartyName;
use easybill\eInvoicing\UBL\Models\PartyTaxScheme;
use easybill\eInvoicing\UBL\Models\Price;
use easybill\eInvoicing\UBL\Models\Quantity;
use easybill\eInvoicing\UBL\Models\TaxCategory;
use easybill\eInvoicing\UBL\Models\TaxScheme;
use easybill\eInvoicing\UBL\Models\TaxSubtotal;
use easybill\eInvoicing\UBL\Models\TaxTotal;
use Illuminate\Support\Facades\Log;

class UBLServiceEasybill
{
    /**
     * Generate UBL XML from Invoice model using easybill/e-invoicing
     */
    public function generate(Invoice $invoice): array
    {
        try {
            $invoiceData = InvoiceData::fromInvoice($invoice);
            $ublInvoice = $this->createUblInvoice($invoiceData);

            // Generate XML
            $transformer = Transformer::create();
            $xml = $transformer->transformToXml($ublInvoice);

            return [
                'success' => true,
                'xml' => $xml,
                'format' => 'UBL',
                'filename' => $this->generateFilename($invoice),
            ];
        } catch (\Exception $e) {
            Log::error('UBL generation failed (easybill)', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Generation failed: '.$e->getMessage(),
                'format' => 'UBL',
            ];
        }
    }

    /**
     * Create UBL Invoice from InvoiceData
     */
    private function createUblInvoice(InvoiceData $invoiceData): UblInvoice
    {
        $invoice = new UblInvoice();

        // Set basic invoice information
        $invoice->customizationId = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
        $invoice->profileId = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
        $invoice->id = $invoiceData->number;
        $invoice->issueDate = $invoiceData->issueDate;
        $invoice->dueDate = $invoiceData->dueDate;
        $invoice->invoiceTypeCode = DocumentType::COMMERCIAL_INVOICE;
        $invoice->documentCurrencyCode = CurrencyCode::tryFrom($invoiceData->currency) ?? CurrencyCode::EUR;
        $invoice->buyerReference = $invoiceData->customer->name ?? 'BUYER-REF';

        // Add notes if available
        if ($invoiceData->notes) {
            $note = new \easybill\eInvoicing\UBL\Models\Note();
            $note->value = $invoiceData->notes;
            $invoice->note[] = $note;
        }

        // Set seller
        $seller = $this->createParty($invoiceData->supplier);
        $invoice->accountingSupplierParty = new AccountingParty();
        $invoice->accountingSupplierParty->party = $seller;

        // Set buyer
        $buyer = $this->createParty($invoiceData->customer);
        $invoice->accountingCustomerParty = new AccountingParty();
        $invoice->accountingCustomerParty->party = $buyer;

        // Add invoice lines (only regular items, no tax lines)
        foreach ($invoiceData->lineItems as $itemData) {
            // Skip lines with empty names (these were invoice-level tax lines)
            if (!empty(trim($itemData->name ?? ''))) {
                $line = $this->createInvoiceLine($itemData, $invoiceData->currency);
                $invoice->invoiceLine[] = $line;
            }
        }

        // Add invoice-level discount as AllowanceCharge
        if ($invoiceData->invoiceDiscount > 0) {
            $allowance = new \easybill\eInvoicing\UBL\Models\AllowanceCharge();
            $allowance->chargeIndicator = 'false'; // 'false' = allowance (discount)
            $allowance->amount = new Amount();
            $allowance->amount->currencyID = CurrencyCode::tryFrom($invoiceData->currency) ?? CurrencyCode::EUR;
            $allowance->amount->value = (string) round($invoiceData->invoiceDiscount, 2);
            $allowance->allowanceChargeReason = 'Discount';
            $invoice->allowanceCharge[] = $allowance;
        }

        // Add invoice-level taxes directly as TaxTotal/TaxSubtotal
        // This is the key advantage: we can add taxes without creating invoice lines!
        if ($invoiceData->invoiceLevelTaxAmount > 0 && !empty($invoiceData->taxes)) {
            $taxTotal = new TaxTotal();
            $taxTotal->taxAmount = new Amount();
            $taxTotal->taxAmount->currencyID = CurrencyCode::tryFrom($invoiceData->currency) ?? CurrencyCode::EUR;
            $taxTotal->taxAmount->value = (string) round($invoiceData->taxAmount, 2);

            foreach ($invoiceData->taxes as $taxData) {
                if ($taxData->amount > 0) {
                    $taxSubtotal = new TaxSubtotal();
                    $taxSubtotal->taxableAmount = new Amount();
                    $taxSubtotal->taxableAmount->currencyID = CurrencyCode::tryFrom($invoiceData->currency) ?? CurrencyCode::EUR;
                    $taxSubtotal->taxableAmount->value = (string) round($taxData->baseAmount, 2);
                    
                    $taxSubtotal->taxAmount = new Amount();
                    $taxSubtotal->taxAmount->currencyID = CurrencyCode::tryFrom($invoiceData->currency) ?? CurrencyCode::EUR;
                    $taxSubtotal->taxAmount->value = (string) round($taxData->amount, 2);
                    
                    $taxSubtotal->taxCategory = new TaxCategory();
                    $taxSubtotal->taxCategory->id = new Id();
                    $taxSubtotal->taxCategory->id->value = $taxData->category ?? 'S';
                    
                    if ($taxData->rate > 0) {
                        $taxSubtotal->taxCategory->percent = (string) round($taxData->rate, 2);
                    }
                    
                    $taxSubtotal->taxCategory->taxScheme = new TaxScheme();
                    $taxSubtotal->taxCategory->taxScheme->id = 'VAT';
                    
                    $taxTotal->taxSubtotal[] = $taxSubtotal;
                }
            }

            $invoice->taxTotal[] = $taxTotal;
        }

        // Set legal monetary total
        $legalMonetaryTotal = new LegalMonetaryTotal();
        $legalMonetaryTotal->lineExtensionAmount = new Amount();
        $legalMonetaryTotal->lineExtensionAmount->currencyID = CurrencyCode::tryFrom($invoiceData->currency) ?? CurrencyCode::EUR;
        $legalMonetaryTotal->lineExtensionAmount->value = (string) round($invoiceData->netAmount, 2);
        
        $legalMonetaryTotal->taxExclusiveAmount = new Amount();
        $legalMonetaryTotal->taxExclusiveAmount->currencyID = CurrencyCode::from($invoiceData->currency);
        $legalMonetaryTotal->taxExclusiveAmount->value = (string) round($invoiceData->netAmount, 2);
        
        $legalMonetaryTotal->taxInclusiveAmount = new Amount();
        $legalMonetaryTotal->taxInclusiveAmount->currencyID = CurrencyCode::from($invoiceData->currency);
        $legalMonetaryTotal->taxInclusiveAmount->value = (string) round($invoiceData->totalAmount, 2);
        
        $legalMonetaryTotal->payableAmount = new Amount();
        $legalMonetaryTotal->payableAmount->currencyID = CurrencyCode::from($invoiceData->currency);
        $legalMonetaryTotal->payableAmount->value = (string) round($invoiceData->totalAmount, 2);
        
        $invoice->legalMonetaryTotal = $legalMonetaryTotal;

        return $invoice;
    }

    /**
     * Create Party from PartyData
     */
    private function createParty($partyData): Party
    {
        $party = new Party();

        // Set electronic address
        if ($partyData->electronicAddress) {
            $party->endpointId = new EndpointId();
            // Map schemeId to ElectronicAddressScheme enum
            $schemeId = $partyData->electronicAddress->schemeId;
            $party->endpointId->schemeID = ElectronicAddressScheme::tryFrom($schemeId) ?? ElectronicAddressScheme::ELECTRONIC_MAIL;
            $party->endpointId->value = $partyData->electronicAddress->value;
        } else {
            // Fallback to email
            $party->endpointId = new EndpointId();
            $party->endpointId->schemeID = ElectronicAddressScheme::ELECTRONIC_MAIL;
            $party->endpointId->value = $partyData->email ?? 'noreply@company.local';
        }

        // Set party name
        $party->partyName = new PartyName();
        $party->partyName->name = $partyData->name ?? 'Not specified';

        // Set company ID
        if ($partyData->companyId) {
            $partyTaxScheme = new PartyTaxScheme();
            $partyTaxScheme->companyId = $partyData->companyId->value;
            $partyTaxScheme->taxScheme = new TaxScheme();
            $partyTaxScheme->taxScheme->id = 'VAT';
            $party->partyTaxScheme[] = $partyTaxScheme;
        } elseif ($partyData->taxId) {
            $partyTaxScheme = new PartyTaxScheme();
            $partyTaxScheme->companyId = $partyData->taxId;
            $partyTaxScheme->taxScheme = new TaxScheme();
            $partyTaxScheme->taxScheme->id = 'VAT';
            $party->partyTaxScheme[] = $partyTaxScheme;
        }

        // Set address
        $address = $partyData->address ?? $partyData->billingAddress ?? null;
        if ($address) {
            $party->postalAddress = new Address();
            if ($address->street) {
                $party->postalAddress->streetName = $address->street;
            }
            if ($address->city) {
                $party->postalAddress->cityName = $address->city;
            }
            if ($address->postalCode) {
                $party->postalAddress->postalZone = $address->postalCode;
            }
            if ($address->countryCode) {
                $party->postalAddress->country = new Country();
                $party->postalAddress->country->identificationCode = CountryCode::tryFrom($address->countryCode) ?? CountryCode::FR;
            } else {
                $party->postalAddress->country = new Country();
                $party->postalAddress->country->identificationCode = CountryCode::FR;
            }
        }

        // Set legal entity
        $party->partyLegalEntity = new PartyLegalEntity();
        $party->partyLegalEntity->registrationName = $partyData->registrationName ?? $partyData->name ?? 'Not specified';

        return $party;
    }

    /**
     * Create InvoiceLine from LineItemData
     */
    private function createInvoiceLine($itemData, string $currency): InvoiceLine
    {
        $line = new InvoiceLine();

        // Set line ID
        $line->id = new Id();
        $line->id->value = $itemData->id;
        
        // Create item
        $item = new Item();
        $item->name = $itemData->name ?? 'Item';
        $item->description = $itemData->description ?? $itemData->name ?? 'Item';
        
        // Set tax category on item
        if ($itemData->taxRate > 0) {
            $taxCategory = new TaxCategory();
            $taxCategory->id = new Id();
            $taxCategory->id->value = $itemData->taxCategory ?? 'S';
            $taxCategory->percent = (string) round($itemData->taxRate, 2);
            $taxCategory->taxScheme = new TaxScheme();
            $taxCategory->taxScheme->id = 'VAT';
            $item->classifiedTaxCategory = $taxCategory;
        }
        
        $line->item[] = $item;

        // Set price
        $line->price = new Price();
        $line->price->priceAmount = new Amount();
        $line->price->priceAmount->currencyID = CurrencyCode::tryFrom($currency) ?? CurrencyCode::EUR;
        $line->price->priceAmount->value = (string) round($itemData->unitPrice, 2);
        
        // Set quantity
        $line->invoicedQuantity = new Quantity();
        $line->invoicedQuantity->unitCode = UnitCode::tryFrom($itemData->unit ?? 'C62') ?? UnitCode::C62;
        $line->invoicedQuantity->value = (string) round($itemData->quantity, 2);

        // Set line extension amount
        $line->lineExtensionAmount = new Amount();
        $line->lineExtensionAmount->currencyID = CurrencyCode::tryFrom($currency) ?? CurrencyCode::EUR;
        $line->lineExtensionAmount->value = (string) round($itemData->netAmount, 2);

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
