<?php

namespace App\Domains\Metadata\Application;

use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Database\Eloquent\Model;

class EloquentCustomFieldValueWriter implements CustomFieldValueWriter
{
    public function attach(Model $valuable, iterable $customFields): void
    {
        foreach ($customFields as $field) {
            $field = $this->normalize($field);
            $customField = $this->definitionFor($valuable, $field['id'] ?? null);

            if (! $customField) {
                continue;
            }

            $valuable->fields()->create([
                'type' => $customField->type,
                'custom_field_id' => $customField->id,
                'company_id' => $customField->company_id,
                getCustomFieldValueKey($customField->type) => $field['value'] ?? null,
            ]);
        }
    }

    public function update(Model $valuable, iterable $customFields): void
    {
        foreach ($customFields as $field) {
            $field = $this->normalize($field);
            $customField = $this->definitionFor($valuable, $field['id'] ?? null);

            if (! $customField) {
                continue;
            }

            // Matched on the definition alone. Matching on the type as well
            // would miss the existing answer for any definition whose type had
            // been edited since it was answered, and write a second row
            // alongside the first rather than replacing it.
            $answer = $valuable->fields()->firstOrNew([
                'custom_field_id' => $customField->id,
            ]);

            $answer->forceFill([
                'type' => $customField->type,
                'company_id' => $customField->company_id,
                getCustomFieldValueKey($customField->type) => $field['value'] ?? null,
            ])->save();
        }
    }

    /**
     * The definition an incoming answer names, or null when it names one this
     * record has no business answering.
     *
     * Nothing upstream checks the submitted id: no form request declares a
     * rule for it, so an id belonging to another company, or to no field at
     * all, reaches this class as-is. A record that knows its own company may
     * only answer that company's definitions, which it names through
     * `customFieldCompanyId()` because a company itself carries no
     * `company_id`. A record that belongs to no single company -- a user, who
     * belongs to several -- is left to the definition it names.
     */
    private function definitionFor(Model $valuable, mixed $id): ?CustomField
    {
        if (! is_numeric($id)) {
            return null;
        }

        $query = CustomField::query()->whereKey($id);
        $company = method_exists($valuable, 'customFieldCompanyId')
            ? $valuable->customFieldCompanyId()
            : $valuable->getAttribute('company_id');

        if ($company !== null) {
            $query->where('company_id', $company);
        }

        return $query->first();
    }

    /** @return array{id?: mixed, value?: mixed} */
    private function normalize(mixed $field): array
    {
        return is_array($field) ? $field : (array) $field;
    }
}
