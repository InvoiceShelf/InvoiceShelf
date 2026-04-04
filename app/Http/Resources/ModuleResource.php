<?php

namespace App\Http\Resources;

use App\Models\Module as ModelsModule;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ext = is_array($this->resource) ? $this->resource : (array) $this->resource;
        $moduleName = $ext['module_name'] ?? '';
        $installed = ModelsModule::where('name', $moduleName)->first();

        $latestVersion = $ext['version'] ?? '0.0.0';
        $installedVersion = $installed && $installed->installed ? $installed->version : null;

        return [
            'id' => $ext['slug'] ?? $moduleName,
            'average_rating' => 0,
            'cover' => $ext['cover'] ?? null,
            'slug' => $ext['slug'] ?? '',
            'module_name' => $moduleName,
            'faq' => [],
            'highlights' => '',
            'installed_module_version' => $installedVersion,
            'installed_module_version_updated_at' => $installed?->updated_at?->toIso8601String(),
            'latest_module_version' => $latestVersion,
            'latest_module_version_updated_at' => null,
            'is_dev' => false,
            'license' => $ext['license'] ?? '',
            'long_description' => $ext['description'] ?? '',
            'monthly_price' => 0,
            'name' => $ext['name'] ?? '',
            'purchased' => true,
            'reviews' => [],
            'screenshots' => [],
            'short_description' => $ext['description'] ?? '',
            'type' => 'MONTHLY',
            'yearly_price' => 0,
            'author_name' => $ext['author'] ?? '',
            'author_avatar' => null,
            'installed' => $installed && $installed->installed,
            'enabled' => $installed && $installed->installed ? (bool) $installed->enabled : false,
            'update_available' => $this->updateAvailable($installed, $latestVersion, $ext),
            'video_link' => null,
            'video_thumbnail' => null,
            'links' => $this->buildLinks($ext),
            'repository' => $ext['repository'] ?? '',
            'download_url' => $ext['download_url'] ?? '',
            'tags' => $ext['tags'] ?? [],
            'compatibility' => $ext['compatibility'] ?? [],
        ];
    }

    private function updateAvailable(?ModelsModule $installed, string $latestVersion, array $ext): bool
    {
        if (! $installed || ! $installed->installed) {
            return false;
        }

        if (! version_compare((string) $installed->version, $latestVersion, '<')) {
            return false;
        }

        return $this->isCompatibleWithApp($ext);
    }

    /**
     * @param  array<string, mixed>  $ext
     */
    private function isCompatibleWithApp(array $ext): bool
    {
        $appVersion = $this->resolveApplicationVersionForCompatibility();
        $min = Arr::get($ext, 'compatibility.min_version');
        $max = Arr::get($ext, 'compatibility.max_version');

        if ($min && version_compare($appVersion, (string) $min, '<')) {
            return false;
        }

        if ($max && version_compare($appVersion, (string) $max, '>')) {
            return false;
        }

        return true;
    }

    /**
     * Prefer `settings` table; fall back to version.md (same source as InstallUtils::setCurrentVersion).
     */
    private function resolveApplicationVersionForCompatibility(): string
    {
        $fromDb = Setting::getSetting('version');
        if ($fromDb !== null && $fromDb !== '') {
            return (string) $fromDb;
        }

        $path = base_path('version.md');
        if (is_file($path)) {
            $v = trim((string) file_get_contents($path));

            return $v !== '' ? $v : '0.0.0';
        }

        return '0.0.0';
    }

    /**
     * @param  array<string, mixed>  $ext
     * @return array<int, array<string, mixed>>
     */
    private function buildLinks(array $ext): array
    {
        $links = [];

        if (! empty($ext['repository'])) {
            $links[] = [
                'icon' => 'CodeBracketIcon',
                'label' => 'Source',
                'link' => $ext['repository'],
            ];
        }

        return $links;
    }
}
