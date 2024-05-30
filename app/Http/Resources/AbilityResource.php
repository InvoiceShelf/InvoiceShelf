<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AbilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'entity_id' => $this->entity_id,
            'entity_type' => $this->entity_type,
            'only_owned' => $this->only_owned,
            'options' => $this->options,
            'scope' => $this->scope,
        ];
    }
}
