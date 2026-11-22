<?php

namespace App\Domains\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pause or resume the scheduled reminders for one invoice.
 */
class InvoiceReminderPauseRequest extends FormRequest
{
    /**
     * The controller checks the send gate on the invoice.
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
            'paused' => ['required', 'boolean'],
        ];
    }
}
