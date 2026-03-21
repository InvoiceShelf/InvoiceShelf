<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EstimatePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        if ($request->has('preview')) {
            return $estimate->getPDFData();
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }
}
