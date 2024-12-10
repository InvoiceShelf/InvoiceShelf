<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use Illuminate\Http\Request;

class UpdateSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(SettingRequest $request)
    {
        $this->authorize('manage settings');

        if (isset($request->settings['manage_stock'])) {
            
            $managedStock = $request->settings['manage_stock'];
            
            if (!in_array($managedStock, ['YES', 'NO'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid stock management setting'
                ], 400);
            }
        }
    
        
        
        Setting::setSettings($request->settings);

        return response()->json([
            'success' => true,
            $request->settings,
        ]);
    }
}
