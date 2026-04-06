<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vat_id' => $this->vat_id,
            'tax_id' => $this->tax_id,
            'logo' => $this->logo,
            'logo_path' => $this->logo_path,
            'unique_hash' => $this->unique_hash,
            'owner_id' => $this->owner_id,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'address' => $this->when($this->address()->exists(), function () {
                return new AddressResource($this->address);
            }),
            'owner' => $this->when($this->relationLoaded('owner'), function () {
                return new UserResource($this->owner);
            }),
            'roles' => RoleResource::collection($this->roles),
            'user_role' => $this->getUserRoleTitle(),
        ];
    }

    private function getUserRoleTitle(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return DB::table('assigned_roles')
            ->join('roles', 'roles.id', '=', 'assigned_roles.role_id')
            ->where('assigned_roles.entity_id', $user->id)
            ->where('assigned_roles.entity_type', get_class($user))
            ->where('assigned_roles.scope', $this->id)
            ->value('roles.title');
    }
}
