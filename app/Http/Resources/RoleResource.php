<?php

namespace InvoiceShelf\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use InvoiceShelf\Models\CompanySetting;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'level' => $this->level,
            'formatted_created_at' => $this->getFormattedAt(),
            'abilities' => $this->getAbilities(),
        ];
    }

    public function getFormattedAt()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->scope);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }
}
