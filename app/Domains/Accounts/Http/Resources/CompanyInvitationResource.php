<?php

namespace App\Domains\Accounts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'email' => $this->email,
            'role_ids' => collect($this->role_ids ?: [$this->role_id])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->values()
                ->all(),
            // The token accepts the invitation, so only the person it was sent to sees it
            'token' => $this->when($this->isFor($request), $this->token),
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'company' => $this->when($this->relationLoaded('company'), function () {
                return new CompanyResource($this->company);
            }),
            'role' => $this->when($this->relationLoaded('role'), function () {
                return new RoleResource($this->role);
            }),
            'roles' => $this->when($this->relationLoaded('role'), function () {
                return RoleResource::collection($this->roles());
            }),
            'invited_by' => $this->when($this->relationLoaded('invitedBy'), function () {
                return new UserResource($this->invitedBy);
            }),
        ];
    }

    private function isFor(Request $request): bool
    {
        $email = $request->user()?->email;

        return is_string($email) && strcasecmp($email, (string) $this->email) === 0;
    }
}
