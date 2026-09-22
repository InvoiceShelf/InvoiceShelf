<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Pdf\Rules\PdfTemplateExists;
use App\Support\DocumentTotals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * Validates the estimate write surface and assembles the attributes the service
 * layer persists. Money arrives as integer minor units.
 */
class EstimatesRequest extends FormRequest
{
    use Concerns\ValidatesDocumentTaxPlaceholders;
    use ValidatesCustomFields;

    /**
     * Gatekeeping happens in the controller, against the estimate itself.
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
            'estimate_date' => 'required',
            'expiry_date' => 'nullable',
            'customer_id' => 'required',
            'estimate_number' => ['required', $this->uniqueNumber()],
            'exchange_rate' => $this->foreignCurrency() ? 'required' : 'nullable',
            'discount' => 'numeric|required',
            'discount_val' => 'integer|required',
            'sub_total' => 'integer|required',
            'total' => 'integer|numeric|max:999999999999|required',
            'tax' => 'required',
            'template_name' => ['required', new PdfTemplateExists('estimate')],
            'items' => 'required|array',
            'items.*.description' => 'nullable',
            'items.*' => 'required|max:255',
            'items.*.name' => 'required',
            'items.*.quantity' => 'numeric|required',
            'items.*.price' => 'integer|required',
            ...$this->customFieldRules(),
            ...$this->customFieldRules('items.*.custom_fields'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDocumentTaxPlaceholders($validator);
    }

    /**
     * The stored attributes for a create or an update.
     *
     * Totals are recomputed here from the submitted lines (GHSA-8c69): whatever
     * sub_total / total / tax the client sent is discarded. The document is
     * always denominated in the customer's currency.
     *
     * @return array<string, mixed>
     */
    public function getEstimatePayload()
    {
        $companyId = $this->header('company');
        $rate = CompanySetting::getSetting('currency', $companyId) != $this->currency_id
            ? $this->exchange_rate
            : 1;

        $perItemTax = CompanySetting::getSetting('tax_per_item', $companyId) ?? 'NO ';
        $perItemDiscount = CompanySetting::getSetting('discount_per_item', $companyId) ?? 'NO';

        $sums = DocumentTotals::compute(
            $this->items ?? [],
            $this->taxes ?? [],
            $this->discount_val,
            $perItemTax,
            (bool) $this->tax_included,
            $perItemDiscount
        );

        $sending = $this->has('estimateSend');

        return collect($this->except(['items', 'taxes']))
            ->merge([
                'creator_id' => $this->user()?->id,
                'status' => $sending ? Estimate::STATUS_SENT : Estimate::STATUS_DRAFT,
                'company_id' => $companyId,
                'tax_per_item' => $perItemTax,
                'discount_per_item' => $perItemDiscount,
                'sub_total' => $sums['sub_total'],
                'total' => $sums['total'],
                'tax' => $sums['tax'],
                'exchange_rate' => $rate,
                'base_discount_val' => $this->discount_val * $rate,
                'base_sub_total' => $sums['sub_total'] * $rate,
                'base_total' => $sums['total'] * $rate,
                'base_tax' => $sums['tax'] * $rate,
                'currency_id' => Customer::find($this->customer_id)->currency_id,
            ])
            ->toArray();
    }

    /**
     * Numbers are unique inside a company; on a replace the estimate being
     * written is exempt from its own number.
     */
    private function uniqueNumber(): Unique
    {
        $rule = Rule::unique('estimates')->where('company_id', $this->header('company'));

        return $this->isMethod('PUT')
            ? $rule->ignore($this->route('estimate')->id)
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
