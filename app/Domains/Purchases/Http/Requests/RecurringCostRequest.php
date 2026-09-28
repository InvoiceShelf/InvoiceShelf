<?php

namespace App\Domains\Purchases\Http\Requests;

use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Taxation\Models\TaxType;
use App\Rules\CronFrequency;
use App\Support\Recurrence\RecurringSchedule;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[BodyParameter('template.customFields.*.value', description: 'Answer matching the field type; null clears an optional answer.', type: 'string|float|bool|null')]
class RecurringCostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return self::rulesFor((int) $this->header('company'));
    }

    public function withValidator(Validator $validator): void
    {
        // The service validates the whole template against its bill or expense schema.
        $validator->excludeUnvalidatedArrayKeys = false;
    }

    /**
     * The schedule's own fields; the template is checked by the service,
     * custom field answers included, since which fields apply depends on the
     * mode.
     */
    public static function rulesFor(int $companyId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'mode' => ['required', Rule::in([RecurringCost::MODE_BILL, RecurringCost::MODE_EXPENSE])],
            'frequency' => ['required', 'string', new CronFrequency],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in([RecurringSchedule::ACTIVE, RecurringSchedule::ON_HOLD])],
            'limit_by' => ['required', Rule::in([RecurringSchedule::LIMIT_NONE, RecurringSchedule::LIMIT_COUNT, RecurringSchedule::LIMIT_DATE])],
            'limit_count' => ['nullable', 'required_if:limit_by,COUNT', 'integer', 'between:1,10000'],
            'limit_date' => ['nullable', 'required_if:limit_by,DATE', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'due_days' => ['required_if:mode,BILL', 'nullable', 'integer', 'between:0,3650'],
            'create_as_draft' => ['sometimes', 'boolean'],
            'notify_creator' => ['sometimes', 'boolean'],
            'template' => ['required', 'array'],
        ];
    }

    /**
     * An expense template: the expense form's fields, without the date a run
     * fills in or the receipt a run cannot carry, scoped to the company.
     * The amount is at least one minor unit, since a schedule for nothing is
     * a mistake rather than an expense.
     */
    public static function expenseTemplateRules(int $companyId): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'taxes' => ['sometimes', 'array'],
            'taxes.*.tax_type_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tax_types', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES),
            ],
            'taxes.*.amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
