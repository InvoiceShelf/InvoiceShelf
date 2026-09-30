<?php

namespace App\Domains\Sales\Http\Requests;

use App\Rules\CronFrequency;
use Illuminate\Foundation\Http\FormRequest;

class RecurrenceFrequencyRequest extends FormRequest
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
            'frequency' => ['required', 'string', new CronFrequency],
            'starts_at' => ['nullable', 'date'],
        ];
    }
}
