<?php

namespace InvoiceShelf\Http\Controllers\V1\Customer\General;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Resources\Customer\CustomerResource;
use InvoiceShelf\Models\Currency;
use InvoiceShelf\Models\Module;

class BootstrapController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
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

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'menu' => $menu,
                'current_customer_currency' => Currency::find($customer->currency_id),
                'modules' => Module::where('enabled', true)->pluck('name'),
            ]]);
    }
}
