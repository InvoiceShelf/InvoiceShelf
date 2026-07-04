<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for emailing a credit note (Stornorechnung).
 *
 * PR #536 referenced this class but never created it, so the send endpoint
 * fatally errored. It mirrors SendInvoiceRequest because a credit note is sent
 * through the same channel, with its own subject/body defaults.
 */
class SendCreditNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Endpoint authorization is handled by the CreditNotePolicy in the
     * controller; field-level validation lives here.
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
            'body' => ['required'],
            'subject' => ['required'],
            'from' => ['required'],
            'to' => ['required'],
            'cc' => ['nullable'],
            'bcc' => ['nullable'],
        ];
    }
}
