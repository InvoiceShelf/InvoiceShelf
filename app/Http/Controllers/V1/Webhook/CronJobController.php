<?php

namespace App\Http\Controllers\V1\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class CronJobController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        Artisan::call('schedule:run');

        return response()->json(['success' => true]);
    }
}
