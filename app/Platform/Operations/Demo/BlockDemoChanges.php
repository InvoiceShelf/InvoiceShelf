<?php

namespace App\Platform\Operations\Demo;

use App\Domains\Contacts\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the public demo, refuses the changes that would lock the next visitor
 * out or reach beyond the demo: the shared account's sign-in and profile,
 * the company's identity, its members and roles, its mail and exchange rate
 * providers, module settings, connected apps, and the customer whose portal
 * sign-in the login page offers.
 *
 * Everything else (invoices, customers, settings) stays open to try; the
 * demo is rebuilt on a schedule. The instance's own settings need a super
 * administrator, which the demo does not have.
 */
class BlockDemoChanges
{
    /**
     * Route URIs, by method, that the demo refuses.
     */
    public const BLOCKED = [
        'PUT' => [
            'api/v1/me',
            'api/v1/me/settings',
            'api/v1/company',
            'api/v1/members/{member}',
            'api/v1/roles/{role}',
            'api/v1/exchange-rate-providers/{exchange_rate_provider}',
            'api/v1/modules/{slug}/settings',
        ],
        'PATCH' => [
            'api/v1/members/{member}',
            'api/v1/roles/{role}',
            'api/v1/exchange-rate-providers/{exchange_rate_provider}',
            'api/v1/mcp/connections/{connection}',
        ],
        'POST' => [
            'api/v1/me/upload-avatar',
            'api/v1/company/upload-logo',
            'api/v1/companies',
            'api/v1/companies/delete',
            'api/v1/transfer/ownership/{user}',
            'api/v1/members',
            'api/v1/members/delete',
            'api/v1/roles',
            'api/v1/company/mail/company-config',
            'api/v1/company/mail/company-test',
            'api/v1/exchange-rate-providers',
            'api/v1/{company}/customer/profile',
        ],
        'DELETE' => [
            'api/v1/auth/tokens/{id}',
            'api/v1/members/{member}',
            'api/v1/roles/{role}',
            'api/v1/exchange-rate-providers/{exchange_rate_provider}',
            'api/v1/mcp/connections/{connection}',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoMode::enabled() || $request->route() === null) {
            return $next($request);
        }

        if ($this->blocked($request)) {
            return response()->json([
                'error' => 'demo_mode',
                'message' => 'This is turned off in the demo.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function blocked(Request $request): bool
    {
        $uri = $request->route()->uri();

        if (in_array($uri, self::BLOCKED[$request->method()] ?? [], true)) {
            return true;
        }

        return $this->touchesPortalCustomer($request, $uri);
    }

    /**
     * The customer whose sign-in the login page offers must stay as it is.
     */
    private function touchesPortalCustomer(Request $request, string $uri): bool
    {
        $portalEmail = DemoMode::credentials()['portal_email'];

        if ($uri === 'api/v1/customers/{customer}' && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
            // This runs before route binding, so the parameter is still the id.
            $customer = $request->route('customer');
            $id = $customer instanceof Customer ? $customer->getKey() : $customer;

            return Customer::query()->whereKey($id)->where('email', $portalEmail)->exists();
        }

        if ($uri === 'api/v1/customers/delete' && $request->isMethod('POST')) {
            return Customer::query()
                ->whereIn('id', (array) $request->input('ids', []))
                ->where('email', $portalEmail)
                ->exists();
        }

        return false;
    }
}
