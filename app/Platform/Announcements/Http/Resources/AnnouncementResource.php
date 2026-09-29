<?php

namespace App\Platform\Announcements\Http\Resources;

use App\Platform\Announcements\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An announcement as the super admin manages it, with every translation.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'title' => $this->title,
            'body' => $this->body,
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'level' => $this->level,
            'audience' => $this->audience,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'translations' => (object) ($this->translations ?? []),
            'hidden' => $this->hidden_at !== null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
