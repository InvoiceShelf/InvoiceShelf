<?php

namespace App\Platform\Announcements\Http\Requests;

use App\Platform\Announcements\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An announcement written on this install: plain text and one optional
 * link, with its text per language.
 */
class AnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'link_url' => ['nullable', 'url:https', 'max:500'],
            'link_label' => ['nullable', 'required_with:link_url', 'string', 'max:60'],
            'level' => ['required', Rule::in(Announcement::LEVELS)],
            'audience' => ['required', Rule::in(Announcement::AUDIENCES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array:title,body,link_label'],
            'translations.*.title' => ['nullable', 'string', 'max:160'],
            'translations.*.body' => ['nullable', 'string', 'max:2000'],
            'translations.*.link_label' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Translations are keyed by one of the app's languages.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $known = array_column((array) config('invoiceshelf.languages'), 'code');

                foreach (array_keys((array) $this->input('translations', [])) as $locale) {
                    if (! in_array($locale, $known, true)) {
                        $validator->errors()->add('translations', "Unknown language: {$locale}.");
                    }
                }
            },
        ];
    }
}
