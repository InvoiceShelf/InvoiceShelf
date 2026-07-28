<?php

namespace App\Http\Requests;

use App\Rules\PublicHttpUrl;
use App\Support\Pdf\GotenbergHostPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            case 'gotenberg':
                // The operator-declared Gotenberg host skips the private-network
                // check; anything else is still held to it. See GotenbergHostPolicy.
                $isDeclaredHost = GotenbergHostPolicy::isExemptFromPrivateNetworkGuard(
                    $this->input('gotenberg_host')
                );

                return [
                    'pdf_driver' => [
                        'required',
                        'string',
                    ],
                    'gotenberg_host' => [
                        'required',
                        'url',
                        Rule::when(! $isDeclaredHost, [new PublicHttpUrl]),
                    ],
                    'gotenberg_papersize' => [
                        'required',
                        'string',
                        function ($attribute, $value, $fail) {
                            $reg = "/^\d+(pt|px|pc|mm|cm|in) \d+(pt|px|pc|mm|cm|in)$/";
                            if (! preg_match($reg, $value)) {
                                $fail('Invalid papersize, must be in format "210mm 297mm". Accepts: pt,px,pc,mm,cm,in');
                            }
                        },
                    ],
                    'gotenberg_margins' => [
                        'nullable',
                        'string',
                    ],
                ];

            default:
                return [
                    'pdf_driver' => [
                        'required',
                        'string',
                    ],
                ];
        }
    }
}
