<?php

namespace App\Domains\Accounts\Http\Resources;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Models\RolePreset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A role preset for the super administrator's editor. The Owner preset lists
 * the whole catalogue, which is what it always holds.
 *
 * @mixin RolePreset
 */
class RolePresetResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        /** @var RolePreset $preset */
        $preset = $this->resource;

        return [
            'id' => $preset->id,
            'key' => $preset->key,
            'title' => $preset->title,
            'is_owner' => $preset->isOwner(),
            'role_name' => $preset->roleName(),
            'abilities' => $preset->isOwner()
                ? array_column(app(AbilityCatalog::class)->all(), 'ability')
                : ($preset->abilities ?? []),
        ];
    }
}
