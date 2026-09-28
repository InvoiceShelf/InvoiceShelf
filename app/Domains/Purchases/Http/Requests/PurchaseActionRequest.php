<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor((int) $this->header('company'));
    }

    public static function rulesFor(int $companyId): array
    {
        return [
            'action' => ['required', Rule::in(['open', 'void', 'pause', 'resume'])],
            'reason' => ['required_if:action,void', 'nullable', 'string', 'max:2000'],
        ];
    }
}
