<?php

namespace App\Platform\Storage\Http\Requests;

/**
 * Shapes the body that rewrites a storage target, or only moves the default flag.
 *
 * A rewrite is validated exactly like a registration. A body that carries no
 * driver is the flag-only call, so the common fields become optional and the
 * per-driver credential rules simply find no driver to key on.
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
