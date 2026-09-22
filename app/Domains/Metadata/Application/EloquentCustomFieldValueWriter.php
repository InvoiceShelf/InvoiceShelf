<?php

namespace App\Domains\Metadata\Application;

use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Database\Eloquent\Model;

class EloquentCustomFieldValueWriter implements CustomFieldValueWriter
{
    public function attach(Model $valuable, iterable $customFields, int|string|null $companyId = null): void
    {
        foreach ($customFields as $field) {
            $field = $this->normalize($field);
            $customField = $this->definitionFor($valuable, $field, $companyId);

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

    public function update(Model $valuable, iterable $customFields, int|string|null $companyId = null): void
    {
        foreach ($customFields as $field) {
            $field = $this->normalize($field);
            $customField = $this->definitionFor($valuable, $field, $companyId);

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
     * An answer names its definition by id or by slug. The slug is the one
     * an integrator can write down: it is minted once and never recomputed,
     * so it survives a rename, and it is what the templates already use.
     *
     * Either way the lookup is scoped to a company. A record that knows its
     * own says so through `customFieldCompanyId()`, because a company itself
     * carries no `company_id`; one that belongs to several, as a user does,
     * relies on the caller passing the company it is acting for. Without a
     * company a slug cannot be resolved at all, since it is unique only
     * within one.
     */
    private function definitionFor(Model $valuable, array $field, int|string|null $companyId): ?CustomField
    {
        $id = $field['id'] ?? null;
        $slug = $field['slug'] ?? null;

        $query = CustomField::query();

        if (is_numeric($id)) {
            $query->whereKey($id);
        } elseif (is_string($slug) && $slug !== '') {
            $query->where('slug', $slug);
        } else {
            return null;
        }

        $company = $companyId ?? (method_exists($valuable, 'customFieldCompanyId')
            ? $valuable->customFieldCompanyId()
            : $valuable->getAttribute('company_id'));

        if ($company !== null) {
            $query->where('company_id', $company);
        } elseif ($id === null) {
            // A slug is unique only within a company, so resolving one
            // without knowing the company would be a guess. An id is not.
            return null;
        }

        return $query->first();
    }

    /** @return array{id?: mixed, value?: mixed} */
    private function normalize(mixed $field): array
    {
        return is_array($field) ? $field : (array) $field;
    }
}
