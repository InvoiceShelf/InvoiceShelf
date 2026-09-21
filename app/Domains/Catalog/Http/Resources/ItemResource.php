<?php

namespace App\Domains\Catalog\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Taxation\Http\Resources\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin payload for a catalogue item.
 *
 * Each nested block is emitted only when its relation actually resolves, so
 * consumers must treat all four as optional: an item with no taxes carries no
 * `taxes` key at all rather than an empty list, and the same goes for an item
 * without a unit. The presence test asks the database directly, which costs
 * one existence query per relation per serialised row.
 */
class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'unit_id' => $this->unit_id,
            'company_id' => $this->company_id,
            'creator_id' => $this->creator_id,
            'currency_id' => $this->currency_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tax_per_item' => $this->tax_per_item,
            'formatted_created_at' => $this->formattedCreatedAt,
            // Loaded by the caller: the listing eager-loads it, so asking
            // the database per row would undo that.
            'fields' => CustomFieldValueResource::collection($this->whenLoaded('fields')),
            'unit' => $this->when(
                $this->unit()->exists(),
                fn () => new UnitResource($this->unit)
            ),
            'company' => $this->when(
                $this->company()->exists(),
                fn () => new CompanyResource($this->company)
            ),
            'taxes' => $this->when(
                $this->taxes()->exists(),
                fn () => TaxResource::collection($this->taxes)
            ),
            'currency' => $this->when(
                $this->currency()->exists(),
                fn () => new CurrencyResource($this->currency)
            ),
        ];
    }
}
