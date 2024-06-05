<?php

namespace App\Http\Controllers\V1\Admin\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Unit::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $units = Unit::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return UnitResource::collection($units);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UnitRequest $request)
    {
        $this->authorize('create', Unit::class);

        $unit = Unit::create($request->getUnitPayload());

        return new UnitResource($unit);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Unit $unit)
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(UnitRequest $request, Unit $unit)
    {
        $this->authorize('update', $unit);

        $unit->update($request->getUnitPayload());

        return new UnitResource($unit);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Unit $unit)
    {
        $this->authorize('delete', $unit);

        if ($unit->items()->exists()) {
            return respondJson('items_attached', 'Items Attached');
        }

        $unit->delete();

        return response()->json([
            'success' => 'Unit deleted successfully',
        ]);
    }
}
