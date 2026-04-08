<?php

namespace App\Http\Resources;

use App\Models\Module as ModelsModule;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

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
        $catalogKind = $ext['catalog_kind'] ?? 'module';

        $latestVersion = $ext['version'] ?? '0.0.0';

        if ($catalogKind === 'pdf_template') {
            $installedVersion = $this->pdfTemplateInstalledVersion($ext);
            $filesOk = $this->pdfTemplateFilesExist($ext);
            $installedFlag = $installedVersion !== null && $filesOk;

            return [
                'id' => $ext['slug'] ?? $moduleName,
                'average_rating' => 0,
                'cover' => $ext['cover'] ?? null,
                'slug' => $ext['slug'] ?? '',
                'module_name' => $moduleName,
                'catalog_kind' => 'pdf_template',
                'pdf_template_type' => $ext['pdf_template_type'] ?? null,
                'template_name' => $ext['template_name'] ?? null,
                'faq' => [],
                'highlights' => '',
                'installed_module_version' => $installedFlag ? $installedVersion : null,
                'installed_module_version_updated_at' => null,
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
                'installed' => $installedFlag,
                'enabled' => $installedFlag,
                'update_available' => $this->updateAvailablePdfTemplate($installedVersion, $latestVersion, $ext, $filesOk),
                'video_link' => null,
                'video_thumbnail' => null,
                'links' => $this->buildLinks($ext),
                'repository' => $ext['repository'] ?? '',
                'download_url' => $ext['download_url'] ?? '',
                'tags' => $ext['tags'] ?? [],
                'compatibility' => $ext['compatibility'] ?? [],
            ];
        }

        $installed = ModelsModule::where('name', $moduleName)->first();

        $installedVersion = $installed && $installed->installed ? $installed->version : null;
        $isLocal = (bool) ($ext['is_local'] ?? false);
        $forcedInstalled = (bool) ($ext['installed'] ?? false);

        return [
            'id' => $ext['slug'] ?? $moduleName,
            'average_rating' => 0,
            'cover' => $ext['cover'] ?? null,
            'slug' => $ext['slug'] ?? '',
            'module_name' => $moduleName,
            'catalog_kind' => 'module',
            'pdf_template_type' => null,
            'template_name' => null,
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
            'installed' => $isLocal ? $forcedInstalled : ($installed && $installed->installed),
            'enabled' => $isLocal
                ? (bool) ($ext['enabled'] ?? false)
                : ($installed && $installed->installed ? (bool) $installed->enabled : false),
            'update_available' => $isLocal ? false : $this->updateAvailable($installed, $latestVersion, $ext),
            'video_link' => null,
            'video_thumbnail' => null,
            'links' => $this->buildLinks($ext),
            'repository' => $ext['repository'] ?? '',
            'download_url' => $ext['download_url'] ?? '',
            'tags' => $ext['tags'] ?? [],
            'compatibility' => $ext['compatibility'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $ext
     */
    private function pdfTemplateInstalledVersion(array $ext): ?string
    {
        $slug = $ext['slug'] ?? '';
        if ($slug === '') {
            return null;
        }

        $json = json_decode(Setting::getSetting('pdf_template_catalog_versions') ?: '{}', true);
        if (! is_array($json)) {
            return null;
        }

        $v = $json[$slug] ?? null;

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    /**
     * @param  array<string, mixed>  $ext
     */
    private function pdfTemplateFilesExist(array $ext): bool
    {
        $type = $ext['pdf_template_type'] ?? '';
        $basename = $ext['template_name'] ?? '';

        if (! in_array($type, ['invoice', 'estimate'], true) || $basename === '') {
            return false;
        }

        return Storage::disk('pdf_templates')->exists($type.'/'.$basename.'.blade.php');
    }

    /**
     * @param  array<string, mixed>  $ext
     */
    private function updateAvailablePdfTemplate(?string $installedVersion, string $latestVersion, array $ext, bool $filesOk): bool
    {
        if (! $installedVersion || ! $filesOk) {
            return false;
        }

        if (! version_compare((string) $installedVersion, $latestVersion, '<')) {
            return false;
        }

        return $this->isCompatibleWithApp($ext);
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
