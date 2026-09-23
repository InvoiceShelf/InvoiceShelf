<?php

namespace App\Platform\Mcp\OAuth;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/**
 * Dynamic client registration, with the redirect origins taken from
 * RedirectPolicy (configuration plus administrator additions) instead of
 * configuration alone.
 */
class RegisterClientController extends OAuthRegisterController
{
    public function __construct(
        private readonly RedirectPolicy $policy,
    ) {}

    /**
     * @return array<int, string>
     */
    protected function allowedDomains(): array
    {
        return array_map(
            fn (string $domain): string => Str::finish($domain, '/'),
            $this->policy->domains(),
        );
    }
}
