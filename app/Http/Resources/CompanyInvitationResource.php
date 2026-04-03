<?php

namespace App\Http\Resources;

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
            'token' => $this->token,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'company' => $this->when($this->relationLoaded('company'), function () {
                return new CompanyResource($this->company);
            }),
            'role' => $this->when($this->relationLoaded('role'), function () {
                return new RoleResource($this->role);
            }),
            'invited_by' => $this->when($this->relationLoaded('invitedBy'), function () {
                return new UserResource($this->invitedBy);
            }),
        ];
    }
}
