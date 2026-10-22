<?php

namespace App\Domains\Accounts\Http\Resources;

use App\Domains\Contacts\Http\Resources\AddressResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A company as the admin API publishes it.
 *
 * Identity, branding and the public handle, plus the postal address when one is
 * on file and the owning account when the caller has already loaded it, and
 * the title of the role the signed-in account holds in this company. The
 * company's roles are not included: the roles endpoint lists them.
 */
class CompanyResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $company = $this->resource;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'vat_id' => $company->vat_id,
            'tax_id' => $company->tax_id,
            'logo' => $company->logo,
            'logo_path' => $company->logo_path,
            'unique_hash' => $company->unique_hash,
            'owner_id' => $company->owner_id,
            'slug' => $company->slug,
            'created_at' => $company->created_at,
            'updated_at' => $company->updated_at,
            'address' => $this->when(
                $company->address()->exists(),
                fn () => new AddressResource($company->address)
            ),
            'owner' => $this->when(
                $company->relationLoaded('owner'),
                fn () => new UserResource($company->owner)
            ),
            'user_role' => $this->assignedRoleTitles(),
            'include_global_roles' => $this->when(
                $company->pivot !== null
                    && array_key_exists('include_global_roles', $company->pivot->getAttributes()),
                fn () => (bool) $company->pivot->include_global_roles
            ),
            'fields' => $this->when(
                $this->fields()->exists(),
                fn () => CustomFieldValueResource::collection($this->fields)
            ),
        ];
    }

    /**
     * Titles of the roles effective for the signed-in account in this company.
     *
     * Direct company roles replace global roles unless the membership opts into
     * combining them. A user with no direct assignment receives the global
     * preset titles, which keeps the company switcher useful for global-only
     * access.
     */
    private function assignedRoleTitles(): ?string
    {
        $viewer = Auth::user();

        if ($viewer === null) {
            return null;
        }

        $directTitles = DB::query()
            ->from('assigned_roles')
            ->join('roles', 'assigned_roles.role_id', '=', 'roles.id')
            ->where([
                ['assigned_roles.entity_type', '=', $viewer->getMorphClass()],
                ['assigned_roles.entity_id', '=', $viewer->id],
                ['assigned_roles.scope', '=', $this->id],
            ])
            ->orderBy('roles.id')
            ->get(['roles.title', 'roles.name'])
            ->map(fn (object $role): string => $role->title ?: $role->name)
            ->filter()
            ->unique()
            ->values();

        $includeGlobalRoles = $this->resource->pivot !== null
            && (bool) $this->resource->pivot->getAttribute('include_global_roles');

        if ($directTitles->isNotEmpty() && $this->resource->pivot === null) {
            $includeGlobalRoles = (bool) DB::table('user_company')
                ->where('user_id', $viewer->id)
                ->where('company_id', $this->id)
                ->value('include_global_roles');
        }

        if ($directTitles->isEmpty() || $includeGlobalRoles) {
            $globalTitles = DB::query()
                ->from('assigned_roles')
                ->join('roles', 'assigned_roles.role_id', '=', 'roles.id')
                ->where([
                    ['assigned_roles.entity_type', '=', $viewer->getMorphClass()],
                    ['assigned_roles.entity_id', '=', $viewer->id],
                ])
                ->whereNull('assigned_roles.scope')
                ->where('roles.name', 'like', 'global:preset:%')
                ->orderBy('roles.id')
                ->get(['roles.title', 'roles.name'])
                ->map(fn (object $role): string => $role->title ?: $role->name)
                ->filter()
                ->unique();

            $directTitles = $directTitles->concat($globalTitles)->unique()->values();
        }

        return $directTitles->isNotEmpty() ? $directTitles->implode(', ') : null;
    }
}
