<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateExpenseRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'expense_date' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }
}
