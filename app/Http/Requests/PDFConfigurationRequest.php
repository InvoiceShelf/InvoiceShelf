<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PDFConfigurationRequest extends FormRequest
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
        switch ($this->get('pdf_driver')) {
            case 'dompdf':
                return [
                    'pdf_driver' => [
                        'required',
                        'string',
                    ],
                ];
                break;

            case 'gotenberg':
                return [
                    'pdf_driver' => [
                        'required',
                        'string',
                    ],
                    'gotenberg_host' => [
                        'required',
                        'url',
                    ],
                    'gotenberg_papersize' => [
                        function ($attribute, $value, $fail) {
                            ($attribute); // unused
                            $reg = "/^\d+(pt|px|pc|mm|cm|in) \d+(pt|px|pc|mm|cm|in)$/";
                            if (! preg_match($reg, $value)) {
                                $fail('Invalid papersize, must be in format "210mm 297mm". Accepts: pt,px,pc,mm,cm,in');
                            }
                        },
                    ],
                ];

                break;
        }
        throw new \InvalidArgumentException('Invalid PDFDriver requested');
    }
}
