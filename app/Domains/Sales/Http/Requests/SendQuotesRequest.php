<?php

namespace App\Domains\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The envelope of a quote mail: who it goes to, what it says, and the
 * optional carbon copies.
 */
class SendQuotesRequest extends FormRequest
{
    /**
     * The send ability is checked in the controller.
     */
    public function authorize(): bool
    {
        return $this->user()->can('send quote', $this->route('quote'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => 'required',
            'body' => 'required',
            'from' => 'required',
            'to' => 'required',
            'cc' => 'nullable',
            'bcc' => 'nullable',
        ];
    }
}
