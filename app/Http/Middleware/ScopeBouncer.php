<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Silber\Bouncer\Bouncer;
use Symfony\Component\HttpFoundation\Response;

class ScopeBouncer
{
    /**
     * The Bouncer instance.
     *
     * @var Bouncer
     */
    protected $bouncer;

    /**
     * Constructor.
     */
    public function __construct(Bouncer $bouncer)
    {
        $this->bouncer = $bouncer;
    }

    /**
     * Set the proper Bouncer scope for the incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $request->header('company');

        if (! $company) {
            $firstCompany = $user->companies()->first();
            if (! $firstCompany) {
                return $next($request);
            }
            $company = $firstCompany->id;
        }

        $this->bouncer->scope()->to($company);

        return $next($request);
    }
}
