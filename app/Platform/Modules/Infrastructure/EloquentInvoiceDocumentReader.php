<?php

namespace App\Platform\Modules\Infrastructure;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;
use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use InvoiceShelf\Modules\Documents\Data\DocumentLine;
use InvoiceShelf\Modules\Documents\Data\DocumentParty;
use InvoiceShelf\Modules\Documents\Data\DocumentTax;
use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;
use InvoiceShelf\Modules\Documents\Data\PostalAddress;
use InvoiceShelf\Modules\Documents\Data\RelatedDocument;

/**
 * Reads whole invoices for modules, as the SDK's typed document data.
 *
 * Amounts are passed through as stored, never recalculated. The tax category
 * and exemption reason come from each tax row's tax type as it is now, and a
 * line's unit code from the company unit its unit name matches.
 */
class EloquentInvoiceDocumentReader implements InvoiceDocumentReader
{
    public function invoiceDocument(int $companyId, int $invoiceId): ?InvoiceDocument
    {
        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->whereKey($invoiceId)
            ->with([
                'company.address.country',
                'currency',
                'customer.billingAddress.country',
                'customer.shippingAddress.country',
                'items' => fn ($query) => $query->orderBy('id'),
                'items.taxes.taxType',
                'taxes.taxType',
                'relatedInvoice',
            ])
            ->first();

        if ($invoice === null) {
            return null;
        }

        $unitCodes = $this->unitCodes($invoice);
        $companyCurrency = Currency::query()->find(CompanySetting::getSetting('currency', $companyId));

        return new InvoiceDocument(
            id: $invoice->id,
            companyId: $invoice->company_id,
            number: (string) $invoice->invoice_number,
            type: $invoice->isCreditNote() ? InvoiceDocument::TYPE_CREDIT_NOTE : InvoiceDocument::TYPE_INVOICE,
            invoiceDate: (string) $this->date($invoice->invoice_date),
            dueDate: $this->date($invoice->due_date),
            currencyCode: (string) $invoice->currency?->code,
            currencyPrecision: (int) ($invoice->currency?->precision ?? 2),
            taxPerItem: $invoice->tax_per_item === 'YES',
            discountPerItem: $invoice->discount_per_item === 'YES',
            taxIncluded: (bool) $invoice->tax_included,
            subTotal: (int) $invoice->sub_total,
            discountVal: (int) $invoice->discount_val,
            tax: (int) $invoice->tax,
            total: (int) $invoice->total,
            dueAmount: (int) $invoice->due_amount,
            seller: $this->companyParty($invoice->company),
            buyer: $invoice->customer ? $this->customerParty($invoice->customer) : null,
            lines: $invoice->items->map(fn (InvoiceItem $item): DocumentLine => new DocumentLine(
                id: $item->id,
                name: (string) $item->name,
                description: $item->description,
                quantity: (string) $item->quantity,
                price: (int) $item->price,
                discountVal: (int) $item->discount_val,
                total: (int) $item->total,
                unitName: $item->unit_name,
                unitCode: $unitCodes[$this->unitKey($item->unit_name)] ?? null,
                taxes: $item->taxes->map($this->tax(...))->values()->all(),
            ))->values()->all(),
            taxes: $invoice->taxes->map($this->tax(...))->values()->all(),
            referenceNumber: $invoice->reference_number,
            notes: $this->notes($invoice),
            creditReason: $invoice->credit_reason,
            related: $invoice->relatedInvoice ? new RelatedDocument(
                $invoice->relatedInvoice->id,
                (string) $invoice->relatedInvoice->invoice_number,
                $this->date($invoice->relatedInvoice->invoice_date),
            ) : null,
            deliveryAddress: $this->address($invoice->customer?->shippingAddress),
            exchangeRate: $invoice->exchange_rate === null ? null : (string) $invoice->exchange_rate,
            companyCurrencyCode: $companyCurrency?->code,
        );
    }

    public function seller(int $companyId): ?DocumentParty
    {
        $company = Company::query()->with('address.country')->find($companyId);

        return $company ? $this->companyParty($company) : null;
    }

    private function companyParty(Company $company): DocumentParty
    {
        return new DocumentParty(
            name: (string) $company->name,
            vatId: $company->vat_id,
            taxId: $company->tax_id,
            phone: $company->address?->phone,
            address: $this->address($company->address),
        );
    }

    private function customerParty(Customer $customer): DocumentParty
    {
        return new DocumentParty(
            name: (string) $customer->name,
            companyName: $customer->company_name,
            taxId: $customer->tax_id,
            email: $customer->email,
            phone: $customer->phone,
            contactName: $customer->contact_name,
            address: $this->address($customer->billingAddress),
        );
    }

    private function address(?Address $address): ?PostalAddress
    {
        if ($address === null) {
            return null;
        }

        return new PostalAddress(
            street1: $address->address_street_1,
            street2: $address->address_street_2,
            city: $address->city,
            state: $address->state,
            zip: $address->zip,
            countryCode: $address->country?->code ? strtoupper($address->country->code) : null,
            phone: $address->phone,
        );
    }

    private function tax(Tax $tax): DocumentTax
    {
        return new DocumentTax(
            taxTypeId: $tax->tax_type_id,
            name: (string) $tax->name,
            percent: $tax->percent === null ? null : (string) $tax->percent,
            amount: (int) $tax->amount,
            calculationType: $tax->calculation_type ?: DocumentTax::CALCULATION_PERCENTAGE,
            fixedAmount: $tax->fixed_amount,
            compound: (bool) $tax->compound_tax,
            categoryCode: $tax->taxType?->tax_category_code,
            exemptionReason: $tax->taxType?->tax_exemption_reason,
        );
    }

    /**
     * The notes as the PDF prints them, placeholders filled in the company's
     * language, reduced to plain text.
     */
    private function notes(Invoice $invoice): ?string
    {
        if (blank($invoice->notes)) {
            return null;
        }

        $locale = App::getLocale();
        App::setLocale((string) (CompanySetting::getSetting('language', $invoice->company_id) ?: $locale));

        try {
            $html = $invoice->getNotes();
        } finally {
            App::setLocale($locale);
        }

        $text = html_entity_decode(strip_tags((string) preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text) === '' ? null : trim($text);
    }

    /**
     * The unit code of every company unit, keyed by its name in lower case: a
     * line keeps the unit name as typed, and SQL string comparison is case
     * sensitive on some databases and not on others.
     *
     * @return array<string, string>
     */
    private function unitCodes(Invoice $invoice): array
    {
        if ($invoice->items->every(fn (InvoiceItem $item): bool => blank($item->unit_name))) {
            return [];
        }

        return Unit::query()
            ->where('company_id', $invoice->company_id)
            ->get(['name', 'unit_code'])
            ->mapWithKeys(fn (Unit $unit): array => [$this->unitKey($unit->name) => $unit->unit_code ?: Unit::DEFAULT_UNIT_CODE])
            ->all();
    }

    private function unitKey(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return $value ? substr((string) $value, 0, 10) : null;
    }
}
