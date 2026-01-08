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

        foreach ($invoice->items as $item) {
            $quantity = (float) ($item->quantity ?? 0);
            // Round to 2 decimal places as required by EN 16931
            $unitPrice = round((float) ($item->price ?? 0) / 100, 2); // Convert from cents to decimal
            $taxRate = round((float) ($item->taxes->sum('percent') ?? 0), 2);

            // Calculate line amounts from unitPrice * quantity to match library calculation
            // This ensures BR-CO-15 compliance as the library calculates totals from these
            $itemNetAmount = round($unitPrice * $quantity, 2);

            // Calculate tax amount from net amount and tax rate
            $itemTaxAmount = round($itemNetAmount * ($taxRate / 100), 2);

            // Skip items with zero quantity or amount
            if ($quantity <= 0 || $itemNetAmount <= 0) {
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

        // Calculate totals from line items to ensure BR-CO-15 compliance
        // Round to 2 decimal places as required by EN 16931
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
