<?php

namespace InvoiceShelf\Http\Controllers\V1\Installer\General;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Resources\Installer\InstallerResource;
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
        $installer = Auth::guard('installer')->user();

        foreach (\Menu::get('installer_portal_menu')->items->toArray() as $data) {
            if ($installer) {
                $menu[] = [
                    'title' => $data->title,
                    'link' => $data->link->path['url'],
                ];
            }
        }

        return (new InstallerResource($installer))
            ->additional(['meta' => [
                'menu' => $menu,
                'current_installer_currency' => Currency::find($installer->currency_id),
                'modules' => Module::where('enabled', true)->pluck('name'),
            ]]);
    }
}
