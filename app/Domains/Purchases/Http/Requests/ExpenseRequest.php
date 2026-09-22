<?php

namespace App\Domains\Purchases\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseRequest extends FormRequest
{
    use ValidatesCustomFields;

    /**
     * Multipart clients send the nested rows as JSON strings; unpack them
     * before validating.
     *
     * The expense form uploads a receipt, so it posts multipart and every
     * nested value arrives encoded. A rule cannot check a string, so this has
     * to happen before validation rather than in the controller.
     */
    protected function prepareForValidation(): void
    {
        foreach (['taxes', 'customFields'] as $key) {
            $submitted = $this->input($key);

            if (! is_string($submitted)) {
                continue;
            }

            $decoded = json_decode($submitted, true);

            $this->merge([
                $key => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
            ]);
        }
    }

    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for creating or editing an expense.
     */
    public function rules(): array
    {
        $rules = [
            'expense_date' => ['required'],
            'expense_number' => ['nullable', 'string', 'max:255'],
            'expense_category_id' => ['required'],
            'exchange_rate' => ['nullable'],
            'payment_method_id' => ['nullable'],
            'amount' => ['required', 'integer', 'min:0'],
            'customer_id' => ['nullable'],
            'notes' => ['nullable'],
            'currency_id' => ['required'],
            'attachment_receipt' => [
                'nullable',
                'file',
                'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx',
                'max:20000',
            ],
            'taxes' => ['sometimes', 'array'],
            'taxes.*' => ['required', 'array:tax_type_id,amount'],
            'taxes.*.tax_type_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tax_types', 'id')
                    ->where('company_id', $this->header('company'))
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES),
            ],
            'taxes.*.amount' => ['required', 'integer', 'min:0'],
        ];

        $homeCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        if ($homeCurrency && $this->currency_id && $homeCurrency !== $this->currency_id) {
            $rules['exchange_rate'] = ['required'];
        }

        return array_merge($rules, $this->customFieldRules());
    }

    /**
     * The tax rows may never add up to more than the expense itself.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $taxes = $this->input('taxes');

            if (! is_array($taxes)) {
                return;
            }

            if ($validator->errors()->has('taxes') || $validator->errors()->has('taxes.*')) {
                return;
            }

            $taxed = collect($taxes)->sum(
                fn (mixed $row): int => is_array($row) ? (int) ($row['amount'] ?? 0) : 0
            );

            if ($taxed > (int) $this->input('amount')) {
                $validator->errors()->add('taxes', 'The total tax amount may not exceed the expense amount.');
            }
        });
    }

    public function getExpensePayload()
    {
        $homeCurrency = CompanySetting::getSetting('currency', $this->header('company'));
        $chosenCurrency = $this->currency_id;
        $rate = $homeCurrency != $chosenCurrency ? $this->exchange_rate : 1;

        return array_merge(Arr::except($this->validated(), ['taxes', 'customFields']), [
            'creator_id' => $this->user()->id,
            'company_id' => $this->header('company'),
            'exchange_rate' => $rate,
            'base_amount' => $this->amount * $rate,
            'currency_id' => $chosenCurrency,
        ]);
    }
}
