<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInvoiceRequest extends FormRequest
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
            'body' => [
                'required',
            ],
            'subject' => [
                'required',
            ],
            'from' => [
                'required',
            ],
            'to' => [
                'required',
            ],
            'cc' => [
                'nullable',
            ],
            'bcc' => [
                'nullable',
            ],
            'attachments' => [
                'nullable',
                'array',
            ],
            'attachments.*' => [
                'file',
                'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx,csv',
                'max:20000',
            ],
        ];
    }
}
