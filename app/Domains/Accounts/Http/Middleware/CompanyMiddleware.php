<?php

namespace App\Domains\Accounts\Http\Middleware;

use App\Domains\Accounts\Application\UserCompanyAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which company the rest of the request runs against.
 *
 * The caller names a workspace in the `company` header. A header that names
 * nothing the caller belongs to is not an error here: it is quietly overwritten
 * with the first company on the caller's membership list, so everything
 * downstream may read the header without vetting it again. Two situations skip
 * the rewrite -- an install whose membership table has not been created yet, and
 * the platform administrator arriving with no header at all (admin mode).
 */
class CompanyMiddleware
{
    public function __construct(private readonly UserCompanyAccessService $companyAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('user_company')) {
            return $next($request);
        }

        $actor = $request->user();

        if ($actor === null) {
            return $next($request);
        }

        $requested = $request->header('company');

        if ($actor->isSuperAdmin() && ! $requested) {
            return $next($request);
        }

        if ($requested && $this->companyAccess->isRestrictedFromCompany($actor, (int) $requested)) {
            abort(403);
        }

        if ($requested && $this->companyAccess->canAccessCompany($actor, (int) $requested)) {
            return $next($request);
        }

        $fallback = $this->companyAccess->firstAccessibleCompany($actor);

        if ($fallback === null) {
            return $next($request);
        }

        // Preserve the existing behavior for an ordinary foreign or stale
        // header: use the first accessible company. Restrictions are handled
        // above as an explicit hard deny and never reach this fallback.
        $request->headers->set('company', $fallback->id);

        return $next($request);
    }
}
