<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Domains\Sales\Application\Composition\ProposalAttributes;
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
abstract class SalesProposalsRequest extends FormRequest
{
    use Concerns\ValidatesDocumentTaxPlaceholders;
    use ValidatesCustomFields;

    /**
     * Gatekeeping happens in the controller, against the estimate itself.
     */
    abstract protected function documentKind(): string;

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
            $this->documentKind().'_date' => 'required',
            'expiry_date' => 'nullable',
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $this->header('company'))],
            $this->documentKind().'_number' => ['required', $this->uniqueNumber()],
            'exchange_rate' => [$this->foreignCurrency() ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'discount' => 'numeric|required',
            'discount_val' => 'integer|required',
            'sub_total' => 'integer|required',
            'total' => 'integer|numeric|max:999999999999|required',
            'tax' => 'required',
            'template_name' => ['required', new PdfTemplateExists($this->documentKind())],
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
    public function getProposalPayload(): array
    {
        return ProposalAttributes::fromInput($this->all(), $this->header('company'), $this->user()?->id, $this->documentKind());
    }

    /**
     * Numbers are unique inside a company; on a replace the estimate being
     * written is exempt from its own number.
     */
    private function uniqueNumber(): Unique
    {
        $rule = Rule::unique($this->documentKind().'s')->where('company_id', $this->header('company'));

        return $this->isMethod('PUT')
            ? $rule->ignore($this->route($this->documentKind())->id)
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
