<?php

namespace App\Platform\Mcp\OAuth;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\Models\McpConnection;
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
    /**
     * Locale codes whose translation file is named differently, mirroring
     * LOCALE_FILE_MAP in resources/scripts/plugins/i18n.ts.
     */
    private const LOCALE_FILE_MAP = [
        'zh_CN' => 'zh-cn',
        'pt_BR' => 'pt-br',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $messages = [];

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

        $locale = $this->localeFor($user, $companies->first()?->id);

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
            't' => fn (string $key, array $replace = []): string => $this->text($locale, $key, $replace),
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
     * The user's own language, or their company's when they left it at the
     * default, or English.
     */
    private function localeFor(User $user, ?int $companyId): string
    {
        $language = $user->getSettings(['language'])->get('language');

        if ((! $language || $language === 'default') && $companyId !== null) {
            $language = CompanySetting::getSetting('language', $companyId);
        }

        return is_string($language) && $language !== '' && $language !== 'default' ? $language : 'en';
    }

    /**
     * A string from the `mcp` group of the SPA's translation files, which is
     * nested JSON the framework's translator cannot address, with `{name}`
     * placeholders filled in. Falls back to English, then to the key.
     *
     * @param  array<string, string>  $replace
     */
    private function text(string $locale, string $key, array $replace = []): string
    {
        $line = data_get($this->messages($locale), $key) ?? data_get($this->messages('en'), $key);

        if (! is_string($line)) {
            return $key;
        }

        foreach ($replace as $name => $value) {
            $line = str_replace('{'.$name.'}', $value, $line);
        }

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private function messages(string $locale): array
    {
        if (! isset($this->messages[$locale])) {
            $file = lang_path((self::LOCALE_FILE_MAP[$locale] ?? $locale).'.json');
            $decoded = preg_match('/^[A-Za-z_-]+$/', $locale) === 1 && is_file($file)
                ? json_decode((string) file_get_contents($file), true)
                : null;

            $this->messages[$locale] = is_array($decoded) ? $decoded : [];
        }

        return $this->messages[$locale];
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
