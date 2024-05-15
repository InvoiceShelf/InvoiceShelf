<?php

namespace InvoiceShelf\Http\Controllers\V1\Installer\General;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Requests\Installer\InstallerProfileRequest;
use InvoiceShelf\Http\Resources\Installer\InstallerResource;
use InvoiceShelf\Models\Company;

class ProfileController extends Controller
{
    public function updateProfile(Company $company, InstallerProfileRequest $request)
    {
        $installer = Auth::guard('installer')->user();

        $installer->update($request->validated());

        if (isset($request->is_installer_avatar_removed) && (bool) $request->is_installer_avatar_removed) {
            $installer->clearMediaCollection('installer_avatar');
        }
        if ($installer && $request->hasFile('installer_avatar')) {
            $installer->clearMediaCollection('installer_avatar');

            $installer->addMediaFromRequest('installer_avatar')
                ->toMediaCollection('installer_avatar');
        }

        if ($request->billing !== null) {
            $installer->shippingAddress()->delete();
            $installer->addresses()->create($request->getShippingAddress());
        }

        if ($request->shipping !== null) {
            $installer->billingAddress()->delete();
            $installer->addresses()->create($request->getBillingAddress());
        }

        return new InstallerResource($installer);
    }

    public function getUser(Request $request)
    {
        $installer = Auth::guard('installer')->user();

        return new InstallerResource($installer);
    }
}
