<?php

namespace App\Platform\Notifications\Http\Resources;

use App\Platform\Notifications\NotificationCatalogue;
use App\Platform\Notifications\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notice in the bell. The SPA writes the words from the translation keys
 * and params, in the viewer's language.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;
        $catalogue = app(NotificationCatalogue::class);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'group' => $catalogue->has($this->type) ? $catalogue->get($this->type)->group : NotificationType::GROUP_SYSTEM,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'params' => (object) ($data['params'] ?? []),
            'translate' => array_values((array) ($data['translate'] ?? [])),
            'url' => $data['url'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
