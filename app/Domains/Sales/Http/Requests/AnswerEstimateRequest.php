<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Sales\Models\Estimate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A customer's answer to an estimate from the portal: accepted or rejected.
 * Nothing else may be written through this door.
 */
class AnswerEstimateRequest extends FormRequest
{
    /**
     * The controller finds the estimate among the signed-in customer's own.
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
            'status' => ['required', Rule::in([Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED])],
        ];
    }
}
