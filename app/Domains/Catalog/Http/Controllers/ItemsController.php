<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Application\ItemService;
use App\Domains\Catalog\Http\Requests\DeleteItemsRequest;
use App\Domains\Catalog\Http\Requests\ItemsRequest;
use App\Domains\Catalog\Http\Resources\ItemResource;
use App\Domains\Catalog\Models\Item;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * Endpoints for the catalog of sellable items owned by the active company.
 *
 * Removal happens in batches through delete(). The resource routes advertise a
 * per-item delete as well, but nothing here answers it -- kept as is, since
 * the working path has always been the batch one.
 */
class ItemsController extends Controller
{
    /**
     * Rows per page when the caller does not ask for a size of its own.
     */
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly ItemService $itemService,
    ) {}

    /**
     * One page of the company's catalog.
     *
     * The unit table is joined in so a row can carry the name of its unit
     * alongside the item's own columns. Clause order is deliberate: the
     * company narrowing goes on first, the query-string filters after it, and
     * `latest()` trails whatever explicit ordering those filters asked for. A
     * `limit` of "all" makes the paginate scope hand back the whole set.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        $filters = $request->all();

        $items = Item::query()
            ->whereCompany()
            ->leftJoin('units', 'items.unit_id', '=', 'units.id')
            ->applyFilters($filters)
            ->select(['items.*', 'units.name as unit_name'])
            ->latest()
            ->paginateData($request->input('limit', self::DEFAULT_LIMIT));

        return ItemResource::collection($items)->additional([
            'meta' => $this->listingMeta(),
        ]);
    }

    /**
     * A single catalog entry.
     */
    public function show(Item $item)
    {
        $this->authorize('view', $item);

        return new ItemResource($item);
    }

    /**
     * Put a new entry in the catalog, optionally with default taxes of its own.
     *
     * Company, creator and currency are decided from the request context, not
     * from the payload. The answer is 200 rather than 201: the service hands
     * back a freshly read row, so the resource does not see a just-created
     * model. Kept as is.
     */
    public function store(ItemsRequest $request)
    {
        $this->authorize('create', Item::class);

        $companyId = (int) $request->header('company');
        $creatorId = (int) $request->user()->getAuthIdentifier();

        $created = $this->itemService->create(
            $request->validated(),
            $request->input('taxes', []),
            $companyId,
            $creatorId,
            $request->input('customFields'),
        );

        return new ItemResource($created);
    }

    /**
     * Amend an existing entry.
     *
     * The submitted tax list replaces the stored one wholesale; sending an
     * empty list clears the taxes.
     */
    public function update(ItemsRequest $request, Item $item)
    {
        $this->authorize('update', $item);

        $companyId = (int) $request->header('company');

        $updated = $this->itemService->update(
            $item,
            $request->validated(),
            $request->input('taxes', []),
            $companyId,
            $request->input('customFields'),
        );

        return new ItemResource($updated);
    }

    /**
     * Drop a batch of entries at once.
     *
     * What may be removed at all is settled by the request object. The ids it
     * cleared are then narrowed to the active company, so an id belonging
     * elsewhere is quietly skipped rather than refused.
     */
    public function delete(DeleteItemsRequest $request)
    {
        $this->authorize('delete multiple items');

        $ownIds = Item::query()
            ->whereCompany()
            ->whereIn('id', $request->input('ids'))
            ->pluck('id');

        Item::destroy($ownIds);

        return response()->json(['success' => true]);
    }

    /**
     * Extras shipped beside the listing rows: the tax types that may be
     * charged on a sale, newest first, and the size of the catalog. The count
     * covers the whole company and ignores the filters just applied.
     *
     * @return array<string, mixed>
     */
    private function listingMeta(): array
    {
        return [
            'tax_types' => TaxType::query()
                ->whereCompany()
                ->where('transaction_type', TaxType::TRANSACTION_TYPE_SALES)
                ->latest()
                ->get(),
            'item_total_count' => Item::query()->whereCompany()->count(),
        ];
    }
}
