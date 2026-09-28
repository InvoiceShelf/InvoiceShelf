<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Purchases\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'fields' => CustomFieldValueResource::collection($this->whenLoaded('fields')),
            'name' => (string) $this->name,
            'contact_name' => $this->contact_name === null ? null : (string) $this->contact_name,
            'email' => $this->email === null ? null : (string) $this->email,
            'phone' => $this->phone === null ? null : (string) $this->phone,
            'website' => $this->website === null ? null : (string) $this->website,
            'tax_id' => $this->tax_id === null ? null : (string) $this->tax_id,
            'currency_id' => (int) $this->currency_id,
            'expense_category_id' => $this->expense_category_id === null ? null : (int) $this->expense_category_id,
            'payment_terms' => (int) $this->payment_terms,
            'addresses' => $this->addresses ?? [],
            'notes' => $this->notes === null ? null : (string) $this->notes,
            'enabled' => (bool) $this->enabled,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'balances' => SupplierBalanceResource::collection($this->resource->getAttribute('balances') ?? []),
            'activities' => PurchaseActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
