<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendEstimatesRequest;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Markdown;

class SendEstimatePreviewController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->estimateService->sendEstimateData($estimate, $request->all());
        $data['url'] = $estimate->estimatePdfUrl;

        return $markdown->render('emails.send.estimate', ['data' => $data]);
    }
}
