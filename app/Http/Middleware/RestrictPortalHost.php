<?php

namespace App\Http\Middleware;

use App\Domains\Accounts\Models\Company;
use App\Support\Urls\CustomerUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps the customer portal's own host (CUSTOMER_PORTAL_URL) to the portal,
 * and sends customers who land on the app host there.
 *
 * A portal host answers 404 for everything a customer has no business with:
 * the staff sign-in, the staff SPA and API, the installer, MCP. Staff and
 * customer sessions then never share a host, and the app's sign-in page is
 * never one link away from a customer. Nothing changes when the portal has
 * no host of its own.
 */
class RestrictPortalHost
{
    /**
     * What a portal host serves: the portal pages, sign-in and API, the
     * public document pages and PDFs, and what those pages load.
     */
    private const PORTAL_PATHS = [
        '#^up$#',
        '#^sanctum/csrf-cookie$#',
        '#^api/v1/app/version$#',
        '#^[^/]+/customer(/.*)?$#',
        '#^api/v1/[^/]+/customer(/.*)?$#',
        '#^customer/(invoices|estimates|payments)/.+$#',
        '#^(invoices|estimates|payments)/pdf/[^/]+$#',
        '#^modules/(scripts|styles)/[^/]+$#',
    ];

    /**
     * Pages a customer opens from a link, which the app host hands on to the
     * portal host.
     */
    private const CUSTOMER_PAGES = [
        '#^[^/]+/customer(/.*)?$#',
        '#^customer/(invoices|estimates|payments)/view/.+$#',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! CustomerUrl::separate()) {
            return $next($request);
        }

        $path = $request->path();

        if (CustomerUrl::isPortalHost($request->getHost())) {
            if ($path === '/') {
                return $this->portalHome();
            }

            abort_unless($this->matches($path, self::PORTAL_PATHS), 404);

            return $next($request);
        }

        if ($request->isMethod('GET') && $this->matches($path, self::CUSTOMER_PAGES)) {
            return redirect()->away(CustomerUrl::to($request->getRequestUri()), 301);
        }

        return $next($request);
    }

    /**
     * The portal host's root opens the portal when the install has a single
     * company; with several it cannot know which one is meant.
     */
    private function portalHome(): Response
    {
        try {
            $slugs = Company::query()->limit(2)->pluck('slug');
        } catch (Throwable) {
            abort(404);
        }

        abort_unless($slugs->count() === 1, 404);

        return redirect()->to('/'.$slugs->first().'/customer/login');
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
