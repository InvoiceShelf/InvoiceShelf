<?php

namespace App\Platform\Mcp\Http\Controllers\Admin;

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Application\OAuth\OAuthKeyManager;
use App\Platform\Http\Controller;
use App\Platform\Mcp\Application\McpSettings;
use App\Platform\Mcp\Http\Requests\UpdateMcpSettingsRequest;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\OAuth\RedirectPolicy;
use Illuminate\Http\JsonResponse;

/**
 * The super administrator's view of the MCP server: whether it is on, where
 * clients connect, the state of the signing keys and the redirect origins
 * clients may register.
 */
class McpSettingsController extends Controller
{
    public function __construct(
        private readonly McpSettings $settings,
        private readonly OAuthKeyManager $keys,
        private readonly RedirectPolicy $redirects,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->state()]);
    }

    /**
     * Switching the server on creates the signing keys when there are none,
     * from this web request, so the files belong to the user that serves the
     * application.
     */
    public function update(UpdateMcpSettingsRequest $request): JsonResponse
    {
        if ($request->has('redirect_domains')) {
            $this->settings->setExtraRedirectDomains(array_values(array_unique(array_map(
                fn (string $domain): string => rtrim($domain, '/'),
                $request->input('redirect_domains'),
            ))));
        }

        if ($request->has('enabled')) {
            if ($request->boolean('enabled')) {
                $this->keys->ensure();
            }

            $this->settings->setEnabled($request->boolean('enabled'));
        }

        return response()->json(['data' => $this->state()]);
    }

    /**
     * Replace the signing keys. Every connected client is signed out and must
     * connect again.
     */
    public function regenerateKeys(AccessRevoker $revoker): JsonResponse
    {
        abort_if($this->keys->status() === OAuthKeyManager::SOURCE_ENV, 422, 'The keys are set through PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY; replace them there.');

        $this->keys->regenerate();
        $revoker->revokeEverything();

        return response()->json(['data' => $this->state()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        return [
            'enabled' => $this->settings->enabled(),
            'server_url' => url('/mcp'),
            'secure' => str_starts_with(url('/'), 'https://'),
            'key_status' => $this->keys->status(),
            'default_redirect_domains' => $this->redirects->defaults(),
            'redirect_domains' => $this->settings->extraRedirectDomains(),
            'connection_count' => McpConnection::query()->count(),
        ];
    }
}
