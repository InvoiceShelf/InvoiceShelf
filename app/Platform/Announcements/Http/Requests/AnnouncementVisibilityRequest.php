<?php

namespace App\Platform\Announcements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hidden' => ['required', 'boolean'],
        ];
    }
}
