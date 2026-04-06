<?php

namespace App\Http\Controllers\CustomerPortal\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class BootstrapController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        foreach (\Menu::get('customer_portal_menu')->items->toArray() as $data) {
            if ($customer) {
                $menu[] = [
                    'title' => $data->title,
                    'link' => $data->link->path['url'],
                ];
            }
        }

        $companyCurrencyId = CompanySetting::getSetting('currency', $customer->company_id);

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'menu' => $menu,
                'current_customer_currency' => Currency::find($customer->currency_id),
                'current_company_currency' => $companyCurrencyId ? Currency::find($companyCurrencyId) : null,
                'modules' => Module::where('enabled', true)->pluck('name'),
                'current_company_language' => CompanySetting::getSetting('language', $customer->company_id),
            ]]);
    }
}
