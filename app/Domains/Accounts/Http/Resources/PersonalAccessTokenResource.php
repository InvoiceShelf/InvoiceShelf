<?php

namespace App\Domains\Accounts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One signed-in device, as the account that owns it sees it.
 *
 * The token value itself is shown once at sign-in and never again, so what is
 * published is only what identifies a device in a list: the name it was minted
 * under, when it was made, and when it last spoke to the server.
 *
 * `current` marks the token carrying this very request, which is what lets a
 * devices screen say "this device" and warn before signing itself out. A
 * caller authenticated by session cookie holds a transient token with no row
 * behind it, so nothing in the list is current for them.
 */
class PersonalAccessTokenResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $token = $this->resource;

        return [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'current' => $this->carriesRequest($request),
        ];
    }

    /**
     * Is this the token the request came in on?
     */
    private function carriesRequest(Request $request): bool
    {
        $carrier = $request->user()?->currentAccessToken();

        return $carrier instanceof PersonalAccessToken
            && $carrier->getKey() === $this->resource->getKey();
    }
}
