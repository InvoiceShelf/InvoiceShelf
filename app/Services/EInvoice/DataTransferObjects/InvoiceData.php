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

        // Calculate totals (convert from cents to decimal format for EN16931)
        $netAmount = $invoice->sub_total / 100;
        $taxAmount = $invoice->tax / 100;
        $totalAmount = $invoice->total / 100;

        // Build line items
        $lineItems = [];
        foreach ($invoice->items as $item) {
            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->price ?? 0) / 100; // Convert from cents to decimal
            $netAmount = (float) ($item->total ?? 0) / 100; // Convert from cents to decimal
            $taxRate = (float) ($item->taxes->sum('percent') ?? 0);
            $taxAmount = (float) ($item->taxes->sum('amount') ?? 0) / 100; // Convert from cents to decimal

            // Skip items with zero quantity or amount
            if ($quantity <= 0 || $netAmount <= 0) {
                continue;
            }

            // Determine tax category based on tax rate
            $taxCategory = self::determineTaxCategory($taxRate);

            $lineItems[] = new LineItemData(
                id: (string) ($item->id ?? ''),
                name: $item->name ?? 'Item',
                description: $item->description ?? $item->name ?? 'Item',
                quantity: $quantity,
                unitPrice: $unitPrice,
                netAmount: $netAmount,
                taxRate: $taxRate,
                taxAmount: $taxAmount,
                unit: $item->unit_name ?? 'EA',
                itemClassification: 'SERVICES', // Default classification
                taxCategory: $taxCategory,
            );
        }

        // Build taxes
        $taxes = [];
        foreach ($invoice->taxes as $tax) {
            $rate = (float) ($tax->percent ?? 0);
            $amount = (float) ($tax->amount ?? 0) / 100; // Convert from cents to decimal
            $baseAmount = (float) ($tax->base_amount ?? 0) / 100; // Convert from cents to decimal

            // Skip taxes with zero amounts
            if ($amount <= 0 && $baseAmount <= 0) {
                continue;
            }

            $taxes[] = new TaxData(
                type: 'VAT',
                rate: $rate,
                amount: $amount,
                baseAmount: $baseAmount,
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
}
