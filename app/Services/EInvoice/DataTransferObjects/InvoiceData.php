<?php

namespace App\Services\EInvoice\DataTransferObjects;

class InvoiceData
{
    public function __construct(
        public string $id,
        public string $number,
        public string $issueDate,
        public string $dueDate,
        public string $currency,
        public float $totalAmount,
        public float $taxAmount,
        public float $netAmount,
        public SupplierData $supplier,
        public CustomerData $customer,
        public array $lineItems,
        public array $taxes,
        public ?string $notes = null,
        public ?string $paymentTerms = null,
        public ?string $deliveryDate = null,
        public ?string $orderReference = null,
        public ?string $contractReference = null,
        public float $invoiceDiscount = 0.0,
        public float $invoiceLevelFixedTaxAmount = 0.0,
        public float $invoiceLevelTaxAmount = 0.0,
    ) {}

    public static function fromInvoice(\App\Models\Invoice $invoice): self
    {
        $company = $invoice->company;
        $customer = $invoice->customer;

        // Get the user who owns the company
        $user = $company->owner ?? null;

        // Build line items first, then calculate totals from lines
        // This ensures totals match what the einvoicing library will calculate (BR-CO-15 compliance)
        $lineItems = [];
        $calculatedNetAmount = 0.0;
        $calculatedTaxAmount = 0.0;

        // Track items with negative amounts to convert them to AllowanceCharge
        $negativeItems = [];
        
        foreach ($invoice->items as $item) {
            $quantity = (float) ($item->quantity ?? 0);
            // Round to 2 decimal places as required by EN 16931
            $unitPrice = round((float) ($item->price ?? 0) / 100, 2); // Convert from cents to decimal
            
            // Use the item's total (which includes discounts) rather than recalculating
            // This ensures we respect discounts and adjustments applied to items
            $itemNetAmount = round((float) ($item->total ?? 0) / 100, 2);
            
            // EN 16931 does not allow negative net amounts in invoice lines (BT-146)
            // Items with negative amounts must be converted to AllowanceCharge
            if ($itemNetAmount < 0) {
                // Store for conversion to AllowanceCharge
                $negativeItems[] = [
                    'name' => $item->name ?? 'Discount',
                    'description' => $item->description ?? $item->name ?? 'Discount',
                    'amount' => abs($itemNetAmount), // Store as positive for allowance
                ];
                // Subtract from net amount (negative item reduces total)
                $calculatedNetAmount += $itemNetAmount; // Adding negative = subtracting
                continue; // Don't create a line item for negative amounts
            }
            
            // Calculate tax amount and rate
            // If tax_per_item is YES, use the tax amount from the item directly
            // If tax_per_item is NO, items don't have individual taxes (taxes are at invoice level)
            $itemTaxAmount = 0.0;
            $taxRate = 0.0;
            
            if ($invoice->tax_per_item === 'YES') {
                // Use the tax amount from the item (already includes discounts)
                $itemTaxAmount = round((float) ($item->tax ?? 0) / 100, 2);
                
                // Calculate tax rate from item taxes if available, otherwise from tax amount
                if ($item->taxes && $item->taxes->count() > 0) {
                    // Use the sum of tax rates from item taxes
                    $taxRate = round((float) ($item->taxes->sum('percent') ?? 0), 2);
                } else {
                    // Calculate rate from tax amount and net amount
                    // In InvoiceShelf, when tax_per_item is YES, item->total is usually net amount
                    // and item->tax is the tax amount
                    if ($itemNetAmount > 0 && $itemTaxAmount > 0) {
                        $taxRate = round(($itemTaxAmount / $itemNetAmount) * 100, 2);
                    }
                }
            }
            // When tax_per_item is NO, itemTaxAmount stays 0 (taxes are calculated at invoice level)

            // Skip items with zero quantity
            if ($quantity <= 0) {
                continue;
            }

            // Accumulate totals from line items for BR-CO-15 compliance
            $calculatedNetAmount += $itemNetAmount;
            $calculatedTaxAmount += $itemTaxAmount;

            // Determine tax category based on tax rate
            $taxCategory = self::determineTaxCategory($taxRate);

            $lineItems[] = new LineItemData(
                id: (string) ($item->id ?? ''),
                name: $item->name ?? 'Item',
                description: $item->description ?? $item->name ?? 'Item',
                quantity: $quantity,
                unitPrice: $unitPrice,
                netAmount: $itemNetAmount,
                taxRate: $taxRate,
                taxAmount: $itemTaxAmount,
                unit: self::normalizeUnitCode($item->unit_name ?? 'EA'),
                itemClassification: 'SERVICES', // Default classification
                taxCategory: $taxCategory,
            );
        }

        // Calculate invoice-level discount first
        $invoiceDiscount = 0.0;
        if ($invoice->discount_per_item === 'NO' && $invoice->discount_val) {
            $invoiceDiscount = round((float) ($invoice->discount_val ?? 0) / 100, 2);
        }

        // Add negative items as additional discounts (AllowanceCharge)
        foreach ($negativeItems as $negativeItem) {
            $invoiceDiscount += $negativeItem['amount'];
        }

        // Subtract total discount from net amount
        if ($invoiceDiscount > 0) {
            $calculatedNetAmount -= $invoiceDiscount;
        }
        
        // Track invoice-level tax amount separately (for tax_per_item === 'NO')
        // These taxes are stored separately and will be added directly to create TaxSubtotal
        // WITHOUT creating invoice lines (the library will create TaxSubtotal from these tax data)
        $invoiceLevelTaxAmount = 0.0;
        if ($invoice->tax_per_item === 'NO' && !empty($invoice->taxes)) {
            foreach ($invoice->taxes as $tax) {
                // Use values already calculated and stored in database
                $taxAmount = round((float) ($tax->amount ?? 0) / 100, 2);
                
                // Skip taxes with zero amounts
                if ($taxAmount <= 0) {
                    continue;
                }
                
                // Add the tax amount to total
                $calculatedTaxAmount += $taxAmount;
                $invoiceLevelTaxAmount += $taxAmount;
            }
        }

        // Use totals from database - they're already calculated correctly
        // But we still need to verify they match our calculations for BR-CO-15 compliance
        $netAmount = round($calculatedNetAmount, 2);
        $taxAmount = round($calculatedTaxAmount, 2);
        $totalAmount = round($netAmount + $taxAmount, 2);

        // Build taxes
        $taxes = [];
        foreach ($invoice->taxes as $tax) {
            // Round to 2 decimal places as required by EN 16931
            $rate = round((float) ($tax->percent ?? 0), 2);
            $amount = round((float) ($tax->amount ?? 0) / 100, 2); // Convert from cents to decimal
            $baseAmount = round((float) ($tax->base_amount ?? 0) / 100, 2); // Convert from cents to decimal

            // Skip taxes with zero amounts
            if ($amount <= 0 && $baseAmount <= 0) {
                continue;
            }

            // Determine tax category for this tax
            $taxCategory = self::determineTaxCategory($rate);

            $taxes[] = new TaxData(
                type: 'VAT',
                rate: $rate,
                amount: $amount,
                baseAmount: $baseAmount,
                category: $taxCategory,
            );
        }

        return new self(
            id: (string) $invoice->id,
            number: $invoice->invoice_number,
            issueDate: is_string($invoice->invoice_date) ? $invoice->invoice_date : $invoice->invoice_date->format('Y-m-d'),
            dueDate: is_string($invoice->due_date) ? $invoice->due_date : $invoice->due_date->format('Y-m-d'),
            currency: $invoice->currency ? $invoice->currency->code : 'EUR',
            totalAmount: $totalAmount,
            taxAmount: $taxAmount,
            netAmount: $netAmount,
            supplier: SupplierData::fromCompany($company, $user),
            customer: CustomerData::fromCustomer($customer),
            lineItems: $lineItems,
            taxes: $taxes,
            notes: $invoice->notes,
            paymentTerms: $invoice->payment_terms,
            invoiceDiscount: $invoiceDiscount, // Store discount for AllowanceCharge
            invoiceLevelFixedTaxAmount: 0.0, // No longer needed, taxes are added as lines with empty name
            invoiceLevelTaxAmount: $invoiceLevelTaxAmount, // Store invoice-level tax amount
        );
    }

    /**
     * Determine tax category based on tax rate according to EN16931
     *
     * EN 16931 tax categories:
     * - S: Standard rate
     * - Z: Zero rated
     * - E: Exempt from tax
     * - AE: Reverse charge
     * - K: VAT exempt for EEA intra-community supply
     * - G: Free export item, tax not charged
     * - O: Services outside scope of tax
     * - L: Canary Islands general indirect tax
     * - M: Tax for production, services and importation in Ceuta and Melilla
     */
    private static function determineTaxCategory(float $taxRate): string
    {
        if ($taxRate == 0) {
            return 'Z'; // Zero rated
        } elseif ($taxRate > 0) {
            return 'S'; // Standard rate
        } else {
            return 'E'; // Exempt from tax
        }
    }

    /**
     * Normalize unit code to UN/ECE Recommendation 20 compliant code
     *
     * Maps common unit codes to UN/ECE Recommendation 20 codes
     * Common mappings:
     * - EA, PCE, PC, PIECE -> C62 (unit/piece)
     * - HUR, HR, HOUR -> HUR (hour)
     * - DAY, D -> DAY (day)
     * - M, METER, MTR -> MTR (meter)
     * - KG, KILOGRAM -> KGM (kilogram)
     * - L, LITER, LTR -> LTR (liter)
     *
     * @param  string  $unitCode  The original unit code
     * @return string UN/ECE Recommendation 20 compliant code (default: C62 for piece/unit)
     */
    private static function normalizeUnitCode(string $unitCode): string
    {
        // Normalize to uppercase and trim
        $unitCode = strtoupper(trim($unitCode));

        // Mapping of common unit codes to UN/ECE Recommendation 20 codes
        $unitMapping = [
            // Piece/Unit codes
            'EA' => 'C62',      // Each
            'PCE' => 'C62',     // Piece
            'PC' => 'C62',      // Piece
            'PIECE' => 'C62',   // Piece
            'UNIT' => 'C62',    // Unit
            'UN' => 'C62',      // Unit
            'U' => 'C62',       // Unit

            // Time codes
            'HUR' => 'HUR',    // Hour
            'HR' => 'HUR',      // Hour
            'HOUR' => 'HUR',    // Hour
            'H' => 'HUR',       // Hour
            'DAY' => 'DAY',     // Day
            'D' => 'DAY',       // Day
            'WEEK' => 'WEE',    // Week
            'WK' => 'WEE',      // Week
            'MONTH' => 'MON',   // Month
            'MO' => 'MON',      // Month
            'YEAR' => 'ANN',    // Year
            'YR' => 'ANN',      // Year

            // Length codes
            'M' => 'MTR',       // Meter
            'METER' => 'MTR',   // Meter
            'MTR' => 'MTR',     // Meter
            'CM' => 'CMT',      // Centimeter
            'CENTIMETER' => 'CMT',
            'KM' => 'KMT',      // Kilometer
            'KILOMETER' => 'KMT',
            'IN' => 'INH',      // Inch
            'INCH' => 'INH',
            'FT' => 'FOT',      // Foot
            'FOOT' => 'FOT',

            // Weight codes
            'KG' => 'KGM',      // Kilogram
            'KILOGRAM' => 'KGM',
            'G' => 'GRM',       // Gram
            'GRAM' => 'GRM',
            'LB' => 'LBR',      // Pound
            'POUND' => 'LBR',
            'OZ' => 'ONZ',      // Ounce
            'OUNCE' => 'ONZ',

            // Volume codes
            'L' => 'LTR',       // Liter
            'LITER' => 'LTR',
            'LTR' => 'LTR',     // Liter
            'ML' => 'MLT',      // Milliliter
            'MILLILITER' => 'MLT',
            'M3' => 'MTQ',      // Cubic meter
            'CUBICMETER' => 'MTQ',

            // Area codes
            'M2' => 'MTK',      // Square meter
            'SQUAREMETER' => 'MTK',
            'SQFT' => 'FTK',    // Square foot
            'SQUAREFOOT' => 'FTK',

            // Other common codes
            'BOX' => 'BX',      // Box
            'PACK' => 'PA',     // Pack
            'PACKAGE' => 'PA',
            'PK' => 'PA',       // Pack
            'SET' => 'SET',     // Set
            'PAIR' => 'PR',      // Pair
            'PR' => 'PR',       // Pair
        ];

        // Return mapped code if exists, otherwise default to C62 (unit/piece)
        return $unitMapping[$unitCode] ?? 'C62';
    }
}
