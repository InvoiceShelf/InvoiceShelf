<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendEstimatesRequest;
use App\Models\Estimate;
use Illuminate\Mail\Markdown;

class SendEstimatePreviewController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $estimate->sendEstimateData($request->all());
        $data['url'] = $estimate->estimatePdfUrl;

        return $markdown->render('emails.send.estimate', ['data' => $data]);
    }
}
