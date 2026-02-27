<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\InteracETransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncInteracETransferController extends Controller
{
    /**
     * Trigger a sync for the current company.
     */
    public function __invoke(Request $request, InteracETransferService $service): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $companyId = (int) $request->header('company');
        $result = $service->sync($companyId)[$companyId] ?? [];

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
