<?php

namespace App\Platform\Mcp\Http\Resources;

use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin McpConnection
 */
class McpConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'redirect_host' => $this->redirect_host,
            'company' => $this->company ? ['id' => $this->company->id, 'name' => $this->company->name] : null,
            'access' => $this->access,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
