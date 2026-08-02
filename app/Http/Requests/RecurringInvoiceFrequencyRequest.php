<?php

namespace App\Http\Requests;

use App\Rules\ValidCronExpression;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecurringInvoiceFrequencyRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'frequency' => ['required', 'string', new ValidCronExpression],
            'starts_at' => ['required', 'date'],
        ];
    }
}
