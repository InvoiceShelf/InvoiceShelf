<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemService
{
    public function create(Request $request): Item
    {
        $data = $request->validated();
        $data['company_id'] = $request->header('company');
        $data['creator_id'] = Auth::id();
        $data['currency_id'] = CompanySetting::getSetting('currency', $request->header('company'));
        $item = Item::create($data);

        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                $item->tax_per_item = true;
                $item->save();
                $tax['company_id'] = $request->header('company');
                $item->taxes()->create($tax);
            }
        }

        return Item::with('taxes')->find($item->id);
    }

    public function update(Item $item, Request $request): Item
    {
        $item->update($request->validated());

        $item->taxes()->delete();

        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                $item->tax_per_item = true;
                $item->save();
                $tax['company_id'] = $request->header('company');
                $item->taxes()->create($tax);
            }
        }

        return Item::with('taxes')->find($item->id);
    }
}
