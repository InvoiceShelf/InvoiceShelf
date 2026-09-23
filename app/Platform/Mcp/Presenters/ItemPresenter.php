<?php

namespace App\Platform\Mcp\Presenters;

use App\Domains\Catalog\Models\Item;

final class ItemPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => Text::plain($item->description),
            'price' => Money::of($item->price, $item->currency_id),
            'unit' => $item->unit?->name,
            'taxes' => $item->taxes
                ->filter(fn ($tax) => (int) $tax->tax_type_id > 0)
                ->map(fn ($tax) => [
                    'tax_type_id' => (int) $tax->tax_type_id,
                    'name' => $tax->name,
                    'percent' => $tax->calculation_type === 'fixed' ? null : (float) $tax->percent,
                ])
                ->values()
                ->all(),
            'app_url' => Link::to("items/{$item->id}/edit"),
        ];
    }
}
