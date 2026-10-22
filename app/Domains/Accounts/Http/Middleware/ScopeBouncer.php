<?php

namespace App\Domains\Accounts\Http\Middleware;

use App\Domains\Accounts\Application\UserCompanyAccessService;
use Closure;
use Illuminate\Http\Request;
use Silber\Bouncer\Bouncer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points Bouncer at the company the request is acting in.
 *
 * Role and ability rows carry the id of the company they were defined for, and
 * for the rest of this request Bouncer reads only the rows carrying the id set
 * here (plus the unscoped ones -- that tolerance lives in the scope class
 * registered by the app service provider, not here). The header is taken at
 * face value because the company middleware has already replaced anything the
 * caller has no claim to. A request with no header falls back to the caller's
 * first company; a caller who belongs to nothing is left unscoped.
 */
class ScopeBouncer
{
    public function __construct(
        protected Bouncer $bouncer,
        private readonly UserCompanyAccessService $companyAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $scope = $request->header('company');

        if (! $scope) {
            $fallback = $this->companyAccess->firstAccessibleCompany($request->user());

            if ($fallback === null) {
                return $next($request);
            }

            $scope = $fallback->id;
        }

        $this->bouncer->scope()->to($scope);

        return $next($request);
    }
}
