<?php

namespace App\Platform\Operations\Application;

use App\Platform\Modules\Runtime\ModuleAssetVersion;
use App\Platform\Operations\Demo\DemoMode;
use App\Platform\Operations\Managed\ManagedMode;
use App\Platform\Operations\Models\Setting;
use App\Support\PoweredBy;
use App\Support\Urls\CustomerUrl;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Nwidart\Modules\Facades\Module as ModuleFinder;

/**
 * Everything a thin client needs before it can paint its first screen.
 *
 * A browser gets these facts from resources/views/app.blade.php, which a
 * mobile client never loads: the build on disk, the sign-in branding, the page
 * title, the demo flag and the module assets to pull in before the app boots.
 * The two are one contract and must stay in lockstep: a module asset rule
 * added to the shell belongs here as well.
 */
class ClientManifestService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'version' => $this->version(),
            'min_client_version' => (string) config('invoiceshelf.client.min_version'),
            // Media URLs are built from APP_URL, so a client whose server
            // address disagrees with this one is about to load broken images.
            'app_url' => config('app.url'),
            // Never the customer-portal title: that one is per company and
            // this endpoint runs with no tenant in hand.
            'page_title' => get_page_title(null),
            'branding' => $this->branding(),
            'modules' => $this->modules(),
            'demo_mode' => DemoMode::enabled(),
            'demo' => DemoMode::enabled() ? DemoMode::clientState() : null,
            'managed_mode' => ManagedMode::enabled(),
            'managed' => ManagedMode::enabled() ? ManagedMode::clientState() : null,
            'source_url' => PoweredBy::sourceUrl(),
            'customer_portal_url' => CustomerUrl::portalUrl(),
        ];
    }

    /**
     * The build on disk, read exactly as the public version probe reads it.
     */
    private function version(): string
    {
        return (string) preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));
    }

    /**
     * The sign-in screen's look: the same four instance settings the Blade
     * shell shares with the SPA, read from the settings table the way
     * BootstrapController reads them rather than through get_app_setting(),
     * which exists to keep Blade booting on an instance that has no database
     * yet. A setting nobody has stored answers null so the client can fall
     * back to its own defaults.
     *
     * @return array<string, mixed>
     */
    private function branding(): array
    {
        $stored = Setting::getSettings([
            'login_page_logo',
            'login_page_heading',
            'login_page_description',
            'copyright_text',
        ]);

        return [
            'login_page_logo' => $this->storageUrl($stored->get('login_page_logo')),
            'login_page_heading' => $stored->get('login_page_heading'),
            'login_page_description' => $stored->get('login_page_description'),
            'copyright_text' => $stored->get('copyright_text'),
            'powered_by' => PoweredBy::clientState(),
        ];
    }

    /**
     * Turn a stored upload path into an address a foreign origin can fetch.
     */
    private function storageUrl(mixed $value): ?string
    {
        return $value === null ? null : url('/storage/'.$value);
    }

    /**
     * Every module asset the shell would emit, as absolute, content-versioned
     * URLs.
     *
     * Styles and scripts are registered independently, so a module may show up
     * with only one of the two.
     *
     * @return list<array{name: string, version: string|null, script: string|null, style: string|null, supported: bool}>
     */
    private function modules(): array
    {
        $scripts = ModuleRegistry::allScripts();
        $styles = ModuleRegistry::allStyles();

        $names = array_values(array_unique([...array_keys($scripts), ...array_keys($styles)]));
        $versions = $this->versionsFor($names);

        return array_map(fn (string $name): array => [
            'name' => $name,
            'version' => $versions[$name],
            'script' => $this->scriptUrl($name, $scripts[$name] ?? null),
            'style' => $this->assetUrl('styles', $name, $styles[$name] ?? null),
            'supported' => ! $this->isRemote($scripts[$name] ?? null),
        ], $names);
    }

    /**
     * The declared version of the installed module behind each asset name.
     *
     * Nothing ties an asset name to a module: Tasks and Projects registers
     * "tasks-projects" while its module.json calls it "TasksProjects". The
     * slug is what the two usually share, so that is matched first and the
     * module name second. Best-effort by design, and null is a valid answer
     * for a module that publishes assets under a name of its own choosing.
     *
     * @param  list<string>  $names
     * @return array<string, string|null>
     */
    private function versionsFor(array $names): array
    {
        $by_slug = [];
        $by_name = [];

        try {
            foreach (ModuleFinder::all() as $module) {
                $version = $module->get('version');

                if (! is_string($version) || $version === '') {
                    continue;
                }

                $slug = $module->get('slug');

                if (is_string($slug) && $slug !== '') {
                    $by_slug[$slug] = $version;
                }

                $by_name[$module->getName()] = $version;
            }
        } catch (\Throwable) {
            // This endpoint is a client's only way in. One unreadable
            // module.json costs a version number, not the whole manifest.
            return array_fill_keys($names, null);
        }

        $resolved = [];

        foreach ($names as $name) {
            $resolved[$name] = $by_slug[$name] ?? $by_name[$name] ?? null;
        }

        return $resolved;
    }

    /**
     * A script registered as a remote URL is handed back untouched and marked
     * unsupported: it is served from an origin the operator cannot add to this
     * installation's CORS allow-list, so a client has no way to load it.
     */
    private function scriptUrl(string $name, ?string $path): ?string
    {
        if ($this->isRemote($path)) {
            return $path;
        }

        return $this->assetUrl('scripts', $name, $path);
    }

    /**
     * The public asset route for one module asset, versioned by the bytes it
     * serves the way the Blade shell versions it.
     */
    private function assetUrl(string $kind, string $name, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $version = ModuleAssetVersion::forPath($path);

        return url("/modules/{$kind}/{$name}").($version === null ? '' : '?v='.$version);
    }

    private function isRemote(?string $path): bool
    {
        return $path !== null && Str::startsWith($path, ['http://', 'https://']);
    }
}
