<?php

namespace App\Domains\Receivables\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * Validates the payment write surface and assembles the attributes the service
 * layer stores. Money arrives as integer minor units, and the invoices a
 * payment settles are named in its allocation rows.
 */
class PaymentRequest extends FormRequest
{
    use ValidatesCustomFields;

    /**
     * The payment abilities are checked by the controller.
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
            'payment_date' => ['required'],
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('company_id', $this->header('company')),
            ],
            // A rate is only demanded when the payer settles in a currency of
            // their own; it is still checked when volunteered.
            'exchange_rate' => $this->foreignCurrency()
                ? ['required', 'numeric', 'gt:0']
                : ['nullable', 'numeric', 'gt:0'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_number' => ['required', $this->uniqueNumber()],
            // Row-level shape only. What the rows are allowed to add up to, and
            // which invoices they may name, is the allocation engine's call.
            'allocations' => ['sometimes', 'array'],
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['nullable'],
            'notes' => ['nullable'],
            ...$this->customFieldRules(),
        ];
    }

    /**
     * The singular invoice_id link is retired: invoices are reached through
     * allocations. It is refused from here instead of through a rule so the
     * dead field never shows up in the generated API schema.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('invoice_id')) {
                return;
            }

            $validator->errors()->add(
                'invoice_id',
                __('validation.prohibited', ['attribute' => 'invoice id'])
            );
        });
    }

    /**
     * The stored attributes.
     *
     * A payment is always denominated in the customer's currency: at home the
     * rate is 1, abroad it is the submitted one, and the base amount is the
     * converted total rounded to whole minor units.
     */
    public function getPaymentPayload()
    {
        $currencyId = Customer::find($this->customer_id)->currency_id;
        $homeCurrency = CompanySetting::getSetting('currency', $this->header('company'));
        $rate = (string) $homeCurrency !== (string) $currencyId ? (float) $this->exchange_rate : 1;

        return collect($this->validated())
            ->except(['allocations', 'customFields'])
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
                'exchange_rate' => $rate,
                'base_amount' => (int) round($this->amount * $rate),
                'currency_id' => $currencyId,
            ])
            ->toArray();
    }

    /**
     * Numbers are unique inside a company; a replace exempts the payment being
     * written from its own number.
     */
    private function uniqueNumber(): Unique
    {
        $rule = Rule::unique('payments')->where('company_id', $this->header('company'));

        return $this->isMethod('PUT')
            ? $rule->ignore($this->route('payment')->id)
            : $rule;
    }

    /**
     * True when the payer's currency is not the company's own, which is what
     * makes a rate mandatory. An unknown customer or an unset company currency
     * leaves the rate optional; the customer rule reports that instead.
     */
    private function foreignCurrency(): bool
    {
        $homeCurrency = CompanySetting::getSetting('currency', $this->header('company'));
        $payer = Customer::find($this->customer_id);

        if (! $payer || ! $homeCurrency) {
            return false;
        }

        return (string) $payer->currency_id !== $homeCurrency;
    }
}
