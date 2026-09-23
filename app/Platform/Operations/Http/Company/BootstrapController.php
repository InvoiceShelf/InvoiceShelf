<?php

namespace App\Platform\Operations\Http\Company;

use App\Domains\Accounts\Application\MemberVisibleSettings;
use App\Domains\Accounts\Http\Resources\CompanyInvitationResource;
use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Money\Models\Currency;
use App\Platform\Http\Controller;
use App\Platform\Modules\Models\Module;
use App\Platform\Operations\Http\Concerns\GeneratesMenu;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Silber\Bouncer\BouncerFacade;

/**
 * The single round trip that hydrates the admin SPA on load: who is signed in,
 * which workspace they are looking at, and what navigation they may see.
 *
 * Three shapes come out of here -- platform administration, a user who belongs
 * to no company yet, and the ordinary company view -- built from one common
 * envelope so the shared keys cannot drift apart.
 */
class BootstrapController extends Controller
{
    use GeneratesMenu;

    /**
     * Every member gets this payload, so it carries no credentials; see
     * MemberVisibleSettings and Setting::SHELL_SETTINGS.
     */
    public function __construct(private readonly MemberVisibleSettings $memberVisibleSettings) {}

    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $memberships = $user->companies;

        if ($user->isSuperAdmin() && $request->has('admin_mode')) {
            return response()->json($this->administrationView($user, $memberships));
        }

        if ($memberships->isEmpty()) {
            return response()->json($this->envelope($user, Currency::first()));
        }

        return response()->json($this->companyView($request, $user, $memberships));
    }

    /**
     * Re-read the workspace named by the company header. No membership check
     * here; the SPA calls it right after it has switched companies.
     */
    public function currentCompany(Request $request)
    {
        return new CompanyResource(Company::find($request->header('company')));
    }

    /**
     * Everything every variant carries, with the company-scoped slots empty.
     * Each variant fills in the ones that apply to it.
     */
    private function envelope($user, ?Currency $currency): array
    {
        return [
            'current_user' => new UserResource($user),
            'current_user_settings' => $user->getAllSettings(),
            'current_user_abilities' => [],
            'companies' => [],
            'current_company' => null,
            'current_company_settings' => [],
            'current_company_currency' => $currency,
            'config' => config('invoiceshelf'),
            'global_settings' => Setting::getSettings(Setting::SHELL_SETTINGS),
            'main_menu' => [],
            'setting_menu' => [],
            'modules' => [],
            'pending_invitations' => CompanyInvitationResource::collection(
                $this->openInvitations($user)
            ),
        ];
    }

    /**
     * Platform administration: no workspace is selected, so the payload swaps
     * in the admin navigation and lists every company the admin belongs to.
     */
    private function administrationView($user, $memberships): array
    {
        return array_merge($this->envelope($user, Currency::first()), [
            'companies' => CompanyResource::collection($memberships),
            'main_menu' => $this->generateMenu('admin_menu', $user),
            'admin_mode' => true,
        ]);
    }

    /**
     * The ordinary view, scoped to one company.
     */
    private function companyView(Request $request, $user, $memberships): array
    {
        $company = $this->activeCompany($request, $user);

        // The owner gate on navigation entries reads the company header, and
        // the SPA's first call after login carries none: it only learns its
        // workspace from this response. The company middleware fills the
        // header in for everyone except a platform administrator (no header
        // is how admin mode announces itself), so name the workspace here,
        // where admin mode has already been ruled out, and the menus describe
        // the same company the payload does.
        $request->headers->set('company', (string) $company->id);

        // Both menus are resolved against the abilities Bouncer has cached so
        // far, i.e. before the refresh further down. Keep that order.
        $mainMenu = $this->mainMenuWithModules($user);
        $settingMenu = $this->generateMenu('setting_menu', $user);

        // Every member gets this payload, so it carries no credentials: the
        // mail transport and module settings stay behind their own endpoints.
        $companySettings = $this->memberVisibleSettings->all($company->id);

        $currency = $companySettings->has('currency')
            ? Currency::find($companySettings->get('currency'))
            : Currency::first();

        BouncerFacade::refreshFor($user);

        return array_merge($this->envelope($user, $currency), [
            'current_user_abilities' => $user->getAbilities(),
            'companies' => CompanyResource::collection($memberships),
            'current_company' => new CompanyResource($company),
            'current_company_settings' => $companySettings,
            'main_menu' => $mainMenu,
            'setting_menu' => $settingMenu,
            'modules' => Module::where('enabled', true)->pluck('name'),
            'user_menu' => $this->moduleUserMenu(),
        ]);
    }

    /**
     * The workspace this request talks about: the one named by the company
     * header when the user is actually a member of it, their first otherwise.
     */
    private function activeCompany(Request $request, $user): ?Company
    {
        $requested = Company::find($request->header('company'));

        if ($requested && $user->hasCompany($requested->id)) {
            return $requested;
        }

        return $user->companies()->first();
    }

    /**
     * Main navigation with module-registered entries appended. They join the
     * same list so the frontend orders core and module items in one pass.
     */
    private function mainMenuWithModules($user): array
    {
        $menu = $this->generateMenu('main_menu', $user);

        foreach (ModuleRegistry::allMenu() as $slug => $entry) {
            $menu[] = [
                'title' => __($entry['title']),
                'link' => $entry['link'],
                'icon' => $entry['icon'],
                'name' => 'module-'.$slug,
                'group' => $entry['group'] ?? 'modules',
                'group_label' => $entry['group_label'] ?? 'navigation.modules',
                'priority' => $entry['priority'] ?? 100,
            ];
        }

        return $menu;
    }

    /**
     * Module entries for the account dropdown, lightest priority first.
     */
    private function moduleUserMenu(): array
    {
        return collect(ModuleRegistry::allUserMenu())
            ->map(fn (array $entry, string $slug): array => [
                ...$entry,
                'title' => __($entry['title']),
                'name' => 'module-'.$slug,
            ])
            ->sortBy('priority')
            ->values()
            ->all();
    }

    /**
     * Invitations still awaiting this user's answer, with everything the
     * frontend needs to describe them eager-loaded.
     */
    private function openInvitations($user)
    {
        return CompanyInvitation::forUser($user)
            ->pending()
            ->with(['company', 'role', 'invitedBy'])
            ->get();
    }
}
