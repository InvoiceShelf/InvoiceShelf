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
            'user_role' => $this->assignedRoleTitle(),
            'fields' => $this->when(
                $this->fields()->exists(),
                fn () => CustomFieldValueResource::collection($this->fields)
            ),
        ];
    }

    /**
     * Title of the role the signed-in account holds inside this company.
     *
     * Read off the assignment table by company id, so it stays right for a
     * company other than the active one. Null when nobody is signed in, and
     * null when the account has no assignment here.
     */
    private function assignedRoleTitle(): ?string
    {
        $viewer = Auth::user();

        if ($viewer === null) {
            return null;
        }

        return DB::query()
            ->from('assigned_roles')
            ->join('roles', 'assigned_roles.role_id', '=', 'roles.id')
            ->where([
                ['assigned_roles.entity_type', '=', $viewer->getMorphClass()],
                ['assigned_roles.entity_id', '=', $viewer->id],
                ['assigned_roles.scope', '=', $this->id],
            ])
            ->value('roles.title');
    }
}
