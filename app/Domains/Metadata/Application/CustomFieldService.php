<?php

namespace App\Domains\Metadata\Application;

use App\Domains\Metadata\Models\CustomField;

class CustomFieldService
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, mixed $defaultAnswer, int $companyId): CustomField
    {
        $attributes[getCustomFieldValueKey($attributes['type'])] = $defaultAnswer;
        $attributes['company_id'] = $companyId;
        $attributes['slug'] = clean_slug($attributes['model_type'], $attributes['name'], $companyId);

        return CustomField::create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(CustomField $customField, array $attributes, mixed $defaultAnswer): CustomField
    {
        $attributes[getCustomFieldValueKey($attributes['type'])] = $defaultAnswer;
        $customField->update($attributes);

        return $customField;
    }
}
