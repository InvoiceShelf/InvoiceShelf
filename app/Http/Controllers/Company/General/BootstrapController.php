<?php

namespace App\Http\Controllers\Company\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyInvitationResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Setting;
use App\Traits\GeneratesMenuTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Silber\Bouncer\BouncerFacade;

class BootstrapController extends Controller
{
    use GeneratesMenuTrait;

    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $current_user = $request->user();
        $current_user_settings = $current_user->getAllSettings();
        $companies = $current_user->companies;

        $pendingInvitations = CompanyInvitation::forUser($current_user)
            ->pending()
            ->with(['company', 'role', 'invitedBy'])
            ->get();

        $global_settings = Setting::getSettings([
            'api_token',
            'admin_portal_theme',
            'admin_portal_logo',
            'login_page_logo',
            'login_page_heading',
            'login_page_description',
            'admin_page_title',
            'copyright_text',
            'save_pdf_to_disk',
        ]);

        // Super admin mode — return admin-only menu with all companies listed
        if ($current_user->isSuperAdmin() && $request->has('admin_mode')) {
            return response()->json([
                'current_user' => new UserResource($current_user),
                'current_user_settings' => $current_user_settings,
                'current_user_abilities' => [],
                'companies' => CompanyResource::collection($companies),
                'current_company' => null,
                'current_company_settings' => [],
                'current_company_currency' => Currency::first(),
                'config' => config('invoiceshelf'),
                'global_settings' => $global_settings,
                'main_menu' => $this->generateMenu('admin_menu', $current_user),
                'setting_menu' => [],
                'modules' => [],
                'admin_mode' => true,
                'pending_invitations' => CompanyInvitationResource::collection($pendingInvitations),
            ]);
        }

        // User has no companies — return minimal bootstrap
        if ($companies->isEmpty()) {
            return response()->json([
                'current_user' => new UserResource($current_user),
                'current_user_settings' => $current_user_settings,
                'current_user_abilities' => [],
                'companies' => [],
                'current_company' => null,
                'current_company_settings' => [],
                'current_company_currency' => Currency::first(),
                'config' => config('invoiceshelf'),
                'global_settings' => $global_settings,
                'main_menu' => [],
                'setting_menu' => [],
                'modules' => [],
                'pending_invitations' => CompanyInvitationResource::collection($pendingInvitations),
            ]);
        }

        $main_menu = $this->generateMenu('main_menu', $current_user);
        $setting_menu = $this->generateMenu('setting_menu', $current_user);

        $current_company = Company::find($request->header('company'));

        if ((! $current_company) || ($current_company && ! $current_user->hasCompany($current_company->id))) {
            $current_company = $current_user->companies()->first();
        }

        $current_company_settings = CompanySetting::getAllSettings($current_company->id);

        $current_company_currency = $current_company_settings->has('currency')
            ? Currency::find($current_company_settings->get('currency'))
            : Currency::first();

        BouncerFacade::refreshFor($current_user);

        return response()->json([
            'current_user' => new UserResource($current_user),
            'current_user_settings' => $current_user_settings,
            'current_user_abilities' => $current_user->getAbilities(),
            'companies' => CompanyResource::collection($companies),
            'current_company' => new CompanyResource($current_company),
            'current_company_settings' => $current_company_settings,
            'current_company_currency' => $current_company_currency,
            'config' => config('invoiceshelf'),
            'global_settings' => $global_settings,
            'main_menu' => $main_menu,
            'setting_menu' => $setting_menu,
            'modules' => Module::where('enabled', true)->pluck('name'),
            'module_menu' => array_values(ModuleRegistry::allMenu()),
            'pending_invitations' => CompanyInvitationResource::collection($pendingInvitations),
        ]);
    }

    public function currentCompany(Request $request)
    {
        $company = Company::find($request->header('company'));

        return new CompanyResource($company);
    }
}
