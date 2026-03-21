<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\Response;

class UpdateUserSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\UpdateSettingsRequest  $request
     * @return Response
     */
    public function __invoke(UpdateSettingsRequest $request)
    {
        $user = $request->user();

        $user->setSettings($request->settings);

        return response()->json([
            'success' => true,
        ]);
    }
}
