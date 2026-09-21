<?php

namespace App\Domains\Accounts\Http\Resources;

use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff account as the admin API publishes it.
 *
 * Besides the stored columns it answers the two authorisation questions the SPA
 * asks about the caller (whether they own the active company, and whether they
 * are the platform administrator) and carries the roles held in the current
 * scope, the creation date already formatted for the active company, and the
 * currency and companies whenever those exist. The avatar is whatever the model
 * exposes: a media URL, or the literal number zero when none is on file.
 */
class UserResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'contact_name' => $user->contact_name,
            'company_name' => $user->company_name,
            'website' => $user->website,
            'enable_portal' => $user->enable_portal,
            'currency_id' => $user->currency_id,
            'facebook_id' => $user->facebook_id,
            'google_id' => $user->google_id,
            'github_id' => $user->github_id,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'avatar' => $user->avatar,
            'is_owner' => $user->isOwner(),
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->roles,
            'formatted_created_at' => $user->formattedCreatedAt,
            'currency' => $this->when(
                $user->currency()->exists(),
                fn () => new CurrencyResource($user->currency)
            ),
            'companies' => $this->when(
                $user->companies()->exists(),
                fn () => CompanyResource::collection($user->companies)
            ),
            'fields' => $this->when(
                $this->fields()->exists(),
                fn () => CustomFieldValueResource::collection($this->fields)
            ),
        ];
    }
}
