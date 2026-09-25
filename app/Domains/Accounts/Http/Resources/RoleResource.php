<?php

namespace App\Domains\Accounts\Http\Resources;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\RolePreset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single company role, shaped for the role editor.
 *
 * The abilities travel as the live grant set read back through Bouncer rather
 * than as whatever was last submitted, so the payload always reports what the
 * store actually holds.
 */
class RoleResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $role = $this->resource;
        $createdAt = $this->getFormattedAt();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'title' => $role->title,
            'level' => $role->level,
            // The preset this role is a company's copy of, if any: read only here.
            'preset' => RolePreset::keyFromRoleName($role->name),
            'formatted_created_at' => $createdAt,
            'abilities' => $role->getAbilities(),
        ];
    }

    /**
     * The creation date in the date format of the company owning the role.
     *
     * The format follows the role's own scope, not the company the reader is
     * looking in from.
     */
    public function getFormattedAt()
    {
        $format = CompanySetting::getSetting('carbon_date_format', $this->scope);

        return Carbon::parse($this->created_at)->translatedFormat($format);
    }
}
