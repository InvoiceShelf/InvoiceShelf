<?php

namespace App\Http\Requests;

/**
 * A disk rewrite is validated like a registration. A body without a driver is
 * the flag-only call that moves the default, so the common fields are optional.
 */
class UpdateDiskRequest extends DiskEnvironmentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['name'] = ['sometimes', ...$rules['name']];
        $rules['driver'] = ['sometimes', ...$rules['driver']];
        $rules['set_as_default'] = ['sometimes', 'boolean'];

        return $rules;
    }
}
