<?php

namespace App\Platform\Mcp\OAuth;

use App\Domains\Accounts\Application\UserLocale;
use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\Models\McpConnection;
use App\Support\SpaTranslations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

/**
 * The page a user sees when an AI client asks to connect.
 *
 * It is rendered by the server rather than the SPA, because it sits in the
 * middle of an OAuth redirect. It leads with the host the user will be sent
 * back to, since a client's name is whatever it chose to register, and asks
 * the two things the connection is bound to: the company and the access level.
 */
class ConsentScreen
{
    public function __construct(
        private readonly ConnectionService $connections,
    ) {}

    /**
     * @param  array{client: Client, user: User, scopes: array<int, mixed>, request: Request, authToken: string}  $parameters
     */
    public function render(array $parameters): View
    {
        $client = $parameters['client'];
        $user = $parameters['user'];

        $companies = $user->companies()->orderBy('name')->get(['companies.id', 'companies.name']);

        $existing = McpConnection::query()
            ->where('user_id', $user->id)
            ->where('oauth_client_id', $client->getKey())
            ->first();

        $locale = UserLocale::for($user, $companies->first()?->id);

        return view('mcp.consent', [
            'clientId' => $client->getKey(),
            'clientName' => $client->name,
            'redirectHost' => $this->connections->redirectHost($client) ?? '',
            'email' => $user->email,
            'companies' => $companies,
            'selectedCompany' => old('company_id', $existing?->company_id ?? $companies->first()?->id),
            'selectedAccess' => old('access', $existing?->access ?? McpConnection::ACCESS_READ),
            'state' => $parameters['request']->input('state'),
            'authToken' => $parameters['authToken'],
            'stylesheets' => $this->stylesheets(),
            'theme' => get_app_setting('admin_portal_theme') ?? 'invoiceshelf',
            'locale' => $locale,
            'direction' => $this->directionOf($locale),
            't' => fn (string $key, array $replace = []): string => SpaTranslations::get($locale, $key, $replace),
        ]);
    }

    /**
     * `rtl` for a right-to-left language, read from the same list as the app
     * shell (`invoiceshelf.rtl_languages`); left to right when there is none.
     */
    private function directionOf(string $locale): string
    {
        $language = Str::before(str_replace('-', '_', $locale), '_');

        return in_array($language, (array) config('invoiceshelf.rtl_languages', []), true) ? 'rtl' : 'ltr';
    }

    /**
     * The SPA's stylesheet, so the page shares its theme tokens and look.
     *
     * Taken from the Vite manifest entry of the SPA rather than added as an
     * entry of its own: Tailwind already scans resources/views, so every
     * class used here is in that bundle.
     *
     * @return list<string>
     */
    private function stylesheets(): array
    {
        $hot = public_path('hot');

        if (is_file($hot)) {
            return [rtrim((string) file_get_contents($hot)).'/resources/css/invoiceshelf.css'];
        }

        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($manifest), true);

        return array_values(array_map(
            fn (string $file): string => asset('build/'.$file),
            $entries['resources/scripts/main.ts']['css'] ?? [],
        ));
    }
}
