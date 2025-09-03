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
        
        // Calculate totals
        $netAmount = $invoice->sub_total;
        $taxAmount = $invoice->tax;
        $totalAmount = $invoice->total;

        // Build line items
        $lineItems = [];
        foreach ($invoice->items as $item) {
            $lineItems[] = new LineItemData(
                id: (string) $item->id,
                name: $item->name,
                description: $item->description,
                quantity: (float) $item->quantity,
                unitPrice: (float) $item->price,
                netAmount: (float) $item->total,
                taxRate: $item->taxes->sum('percent'),
                taxAmount: $item->taxes->sum('amount'),
                unit: $item->unit_name ?? 'EA',
                itemClassification: 'SERVICES', // Default classification
            );
        }

        // Build taxes
        $taxes = [];
        foreach ($invoice->taxes as $tax) {
            $taxes[] = new TaxData(
                type: 'VAT',
                rate: (float) $tax->percent,
                amount: (float) $tax->amount,
                baseAmount: (float) $tax->base_amount,
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
            supplier: SupplierData::fromCompany($company),
            customer: CustomerData::fromCustomer($customer),
            lineItems: $lineItems,
            taxes: $taxes,
            notes: $invoice->notes,
            paymentTerms: $invoice->payment_terms,
        );
    }
}
