<?php

namespace App\Http\Requests;

use App\Rules\CssLength;
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
        return array_merge($this->pageRules(), $this->driverRules());
    }

    /**
     * Page geometry is driver-neutral, so it is validated the same way whichever
     * driver is selected. Paper size used to live under `gotenberg_papersize` as
     * a single "210mm 297mm" string, which dompdf had no equivalent of and could
     * not consume.
     */
    private function pageRules(): array
    {
        $length = ['nullable', 'string', new CssLength];

        return [
            'pdf_driver' => ['required', 'string'],
            'pdf_paper_width' => ['required', 'string', new CssLength],
            'pdf_paper_height' => ['required', 'string', new CssLength],
            'pdf_orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'pdf_margin_top' => $length,
            'pdf_margin_right' => $length,
            'pdf_margin_bottom' => $length,
            'pdf_margin_left' => $length,
            'pdf_page_numbers' => ['sometimes', 'boolean'],
        ];
    }

    private function driverRules(): array
    {
        if ($this->get('pdf_driver') !== 'gotenberg') {
            return [];
        }

        // The operator-declared Gotenberg host skips the private-network
        // check; anything else is still held to it. See GotenbergHostPolicy.
        $isDeclaredHost = GotenbergHostPolicy::isExemptFromPrivateNetworkGuard(
            $this->input('gotenberg_host')
        );

        return [
            'gotenberg_host' => [
                'required',
                'url',
                Rule::when(! $isDeclaredHost, [new PublicHttpUrl]),
            ],
        ];
    }
}
