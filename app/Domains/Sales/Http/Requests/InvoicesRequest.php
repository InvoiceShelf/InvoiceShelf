<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Pdf\Rules\PdfTemplateExists;
use App\Support\DocumentTotals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * Validates the invoice write surface and assembles the attributes the service
 * layer persists. Money arrives as integer minor units.
 */
class InvoicesRequest extends FormRequest
{
    use Concerns\ValidatesDocumentTaxPlaceholders;
    use ValidatesCustomFields;

    /**
     * Gatekeeping happens in the controller, against the invoice itself.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_date' => 'required',
            'due_date' => 'nullable',
            'customer_id' => 'required',
            'invoice_number' => ['required', $this->uniqueNumber()],
            'exchange_rate' => $this->foreignCurrency() ? 'required' : 'nullable',
            'discount' => 'numeric|required',
            'discount_val' => 'integer|required',
            'sub_total' => 'numeric|required',
            'total' => 'numeric|max:999999999999|required',
            'tax' => 'required',
            'template_name' => ['required', new PdfTemplateExists('invoice')],
            'items' => 'required|array',
            'items.*' => 'required|max:255',
            'items.*.description' => 'nullable',
            'items.*.name' => 'required',
            'items.*.quantity' => 'numeric|required',
            'items.*.price' => 'numeric|required',
            ...$this->customFieldRules(),
            ...$this->customFieldRules('items.*.custom_fields'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDocumentTaxPlaceholders($validator);
        $this->validateCustomFieldAnswers($validator);
        $this->validateCustomFieldAnswers($validator, 'items.*.custom_fields');
    }

    /**
     * The stored attributes for a create or an update.
     *
     * Totals are recomputed here from the submitted lines (GHSA-8c69): whatever
     * sub_total / total / tax the client sent is discarded. The document is
     * always denominated in the customer's currency, and it is never allowed to
     * declare itself a credit note: those are minted by the credit-note service
     * alone.
     *
     * @return array<string, mixed>
     */
    public function getInvoicePayload(): array
    {
        $companyId = $this->header('company');
        $rate = CompanySetting::getSetting('currency', $companyId) != $this->currency_id
            ? $this->exchange_rate
            : 1;

        $perItemTax = CompanySetting::getSetting('tax_per_item', $companyId) ?? 'NO';
        $perItemDiscount = CompanySetting::getSetting('discount_per_item', $companyId) ?? 'NO';
        $taxIncluded = (bool) $this->tax_included;

        $sums = DocumentTotals::compute(
            $this->items ?? [],
            $this->taxes ?? [],
            $this->discount_val,
            $perItemTax,
            $taxIncluded,
            $perItemDiscount
        );

        return array_merge($this->withoutCustomFields($this->except(['items', 'taxes'])), [
            'creator_id' => $this->user()?->id,
            'type' => Invoice::TYPE_INVOICE,
            'related_invoice_id' => null,
            'credit_reason' => null,
            'status' => $this->exists('invoiceSend') ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'company_id' => $companyId,
            'tax_per_item' => $perItemTax,
            'discount_per_item' => $perItemDiscount,
            'sub_total' => $sums['sub_total'],
            'total' => $sums['total'],
            'tax' => $sums['tax'],
            'due_amount' => $sums['total'],
            'sent' => (bool) $this->sent,
            'viewed' => (bool) $this->viewed,
            'exchange_rate' => $rate,
            'base_total' => $sums['total'] * $rate,
            'base_discount_val' => $this->discount_val * $rate,
            'base_sub_total' => $sums['sub_total'] * $rate,
            'base_tax' => $sums['tax'] * $rate,
            'base_due_amount' => $sums['total'] * $rate,
            'currency_id' => Customer::find($this->customer_id)->currency_id,
        ]);
    }

    /**
     * Numbers are unique inside a company; on a replace the invoice being
     * written is exempt from its own number.
     */
    private function uniqueNumber(): Unique
    {
        $rule = Rule::unique('invoices')->where('company_id', $this->header('company'));

        return $this->isMethod('PUT')
            ? $rule->ignore($this->route('invoice')->id)
            : $rule;
    }

    /**
     * True when the billed customer settles in something other than the
     * company's own currency, which makes a rate mandatory.
     */
    private function foreignCurrency(): bool
    {
        $homeCurrency = CompanySetting::getSetting('currency', $this->header('company'));
        $billed = Customer::find($this->customer_id);

        if (! $homeCurrency || ! $billed) {
            return false;
        }

        return (string) $billed->currency_id !== $homeCurrency;
    }
}
