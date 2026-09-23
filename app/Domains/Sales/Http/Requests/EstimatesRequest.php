<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Domains\Sales\Application\Composition\EstimateAttributes;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Pdf\Rules\PdfTemplateExists;
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
        $this->validateCustomFieldAnswers($validator);
        $this->validateCustomFieldAnswers($validator, 'items.*.custom_fields');
    }

    /**
     * The stored attributes for a create or an update.
     *
     * @return array<string, mixed>
     */
    public function getEstimatePayload(): array
    {
        return EstimateAttributes::fromInput($this->all(), $this->header('company'), $this->user()?->id);
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
