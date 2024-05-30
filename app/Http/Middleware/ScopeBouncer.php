<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;
use Silber\Bouncer\Bouncer;

class ScopeBouncer
{
    /**
     * The Bouncer instance.
     *
     * @var \Silber\Bouncer\Bouncer
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
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = $request->header('company')
            ? $request->header('company')
            : $user->companies()->first()->id;

        $this->bouncer->scope()->to($tenantId);

        return $next($request);
    }
}
