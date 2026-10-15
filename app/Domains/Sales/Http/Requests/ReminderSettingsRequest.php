<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Sales\Application\ReminderSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A company's payment reminder settings. The days are counted from the due
 * date, negative before it.
 */
class ReminderSettingsRequest extends FormRequest
{
    /**
     * The controller checks the company gate.
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
        $limit = ReminderSettings::MAX_OFFSET;

        return [
            'enabled' => ['sometimes', 'boolean'],
            'offsets' => ['sometimes', 'array', 'max:'.ReminderSettings::MAX_OFFSETS],
            'offsets.*' => ['integer', 'distinct', "between:-{$limit},{$limit}"],
            'send_hour' => ['sometimes', 'integer', 'between:0,23'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:10000'],
            'attach_pdf' => ['sometimes', 'boolean'],
        ];
    }
}
