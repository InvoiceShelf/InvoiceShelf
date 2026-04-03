<?php

namespace App\Http\Controllers\V1\Admin\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\DeleteItemsRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\TaxType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    /**
     * Retrieve a list of existing Items.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $items = Item::whereCompany()
            ->leftJoin('units', 'units.id', '=', 'items.unit_id')
            ->applyFilters($request->all())
            ->select('items.*', 'units.name as unit_name')
            ->latest()
            ->paginateData($limit);

        return ItemResource::collection($items)
            ->additional(['meta' => [
                'tax_types' => TaxType::whereCompany()->latest()->get(),
                'item_total_count' => Item::whereCompany()->count(),
            ]]);
    }

    /**
     * Create Item.
     *
     * @param  App\Http\Requests\ItemsRequest  $request
     * @return JsonResponse
     */
    public function store(Requests\ItemsRequest $request)
    {
        $this->authorize('create', Item::class);

        $item = Item::createItem($request);

        return new ItemResource($item);
    }

    /**
     * get an existing Item.
     *
     * @return JsonResponse
     */
    public function show(Item $item)
    {
        $this->authorize('view', $item);

        return new ItemResource($item);
    }

    /**
     * Update an existing Item.
     *
     * @param  App\Http\Requests\ItemsRequest  $request
     * @return JsonResponse
     */
    public function update(Requests\ItemsRequest $request, Item $item)
    {
        $this->authorize('update', $item);

        $item = $item->updateItem($request);

        return new ItemResource($item);
    }

    /**
     * Delete a list of existing Items.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteItemsRequest $request)
    {
        $this->authorize('delete multiple items');

        $ids = Item::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        Item::destroy($ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
