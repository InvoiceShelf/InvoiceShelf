<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseAttachmentRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20000'],
        ];
    }
}
