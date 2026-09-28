<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Purchases\Models\PurchaseActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseActivity */
class PurchaseActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'action' => (string) $this->action,
            'created_at' => $this->created_at->toISOString(),
            'actor_id' => $this->actor_id === null ? null : (int) $this->actor_id,
            'details' => (object) ($this->details ?? []),
        ];
    }
}
