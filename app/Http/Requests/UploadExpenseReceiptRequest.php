<?php

namespace InvoiceShelf\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use InvoiceShelf\Rules\Base64Mime;

class UploadExpenseReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'attachment_receipt' => [
                'nullable',
                new Base64Mime(['gif', 'jpg', 'png']),
            ],
        ];
    }
}
