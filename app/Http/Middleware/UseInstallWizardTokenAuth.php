<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\Setup\InstallUtils;
use App\Support\InstallWizardAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseInstallWizardTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallWizardAuth::isRequest($request) || ! $this->installationIsIncomplete()) {
            return $next($request);
        }

        config([
            'sanctum.guard' => [],
            'sanctum.stateful' => [],
        ]);
        $request->attributes->set('install_wizard', true);

        return $next($request);
    }

    private function installationIsIncomplete(): bool
    {
        if (! InstallUtils::isDbCreated()) {
            return true;
        }

        try {
            return Setting::getSetting('profile_complete') !== 'COMPLETED';
        } catch (\Exception $e) {
            return true;
        }
    }
}
