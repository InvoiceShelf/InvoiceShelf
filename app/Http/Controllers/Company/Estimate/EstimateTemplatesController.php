<?php

namespace App\Http\Controllers\Company\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Services\Pdf\PdfTemplateUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstimateTemplatesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $estimateTemplates = PdfTemplateUtils::getFormattedTemplates('estimate');

        return response()->json([
            'estimateTemplates' => $estimateTemplates,
        ]);
    }
}
