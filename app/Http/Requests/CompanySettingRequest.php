<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
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
        return [
            'currency' => [
                'required',
            ],
            'time_zone' => [
                'required',
            ],
            'language' => [
                'required',
            ],
            'fiscal_year' => [
                'required',
            ],
            'moment_date_format' => [
                'required',
            ],
            'carbon_date_format' => [
                'required',
            ],
            'moment_time_format' => [
                'required',
            ],
            'carbon_time_format' => [
                'required',
            ],
            'invoice_use_time' => [
                'required',
            ],
        ];
    }
}
