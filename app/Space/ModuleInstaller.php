<?php

namespace App\Space;

use App\Events\ModuleEnabledEvent;
use App\Events\ModuleInstalledEvent;
use App\Http\Resources\ModuleResource;
use App\Models\Module as ModelsModule;
use App\Models\Setting;
use App\Services\ExtensionCatalog;
use App\Services\PdfTemplateCatalog;
use Artisan;
use File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Finder\Finder;
use ZipArchive;

// Implementation taken from Akaunting - https://github.com/akaunting/akaunting
class ModuleInstaller
{
    private const int MAX_PACKAGE_BYTES = 50_000_000; // 50 MB

    private const int MAX_EXTRACTED_FILES = 2_000;

    private const int MAX_EXTRACTED_BYTES = 200_000_000; // 200 MB

    /**
     * @return JsonResponse|AnonymousResourceCollection
     */
    public static function getModules()
    {
        $items = self::fetchAllCatalogItems();
        $localModules = self::fetchLocalModules();

        if ($items === [] && $localModules === []) {
            return response()->json(['message' => 'extensions_catalog_unavailable'], 503);
        }

        $merged = self::mergeCatalogAndLocalModules($items, $localModules);

        return ModuleResource::collection(collect($merged))
            ->additional(['meta' => [
                'catalog_unavailable' => $items === [],
            ]]);
    }

    /**
     * @return \stdClass
     */
    public static function getModule(string $slug)
    {
        $items = self::fetchAllCatalogItems();

        if ($items === []) {
            $local = self::findLocalModuleBySlug($slug);

            if ($local !== null) {
                return (object) [
                    'success' => true,
                    'module' => $local,
                    'modules' => array_slice(self::fetchLocalModules(), 0, 8),
                ];
            }

            return (object) ['success' => false, 'error' => 'catalog_unavailable'];
        }

        $found = collect($items)->firstWhere('slug', $slug);

        if (! $found) {
            $local = self::findLocalModuleBySlug($slug);

            if ($local !== null) {
                $others = collect(self::mergeCatalogAndLocalModules($items, self::fetchLocalModules()))
                    ->filter(fn (array $e) => ($e['slug'] ?? '') !== $slug)
                    ->take(8)
                    ->values()
                    ->all();

                return (object) [
                    'success' => true,
                    'module' => $local,
                    'modules' => $others,
                ];
            }

            return (object) ['success' => false, 'error' => 'not_found'];
        }

        $others = collect($items)
            ->filter(fn (array $e) => ($e['slug'] ?? '') !== $slug)
            ->take(8)
            ->values()
            ->all();

        return (object) [
            'success' => true,
            'module' => $found,
            'modules' => $others,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchLocalModules(): array
    {
        $modulesPath = (string) config('modules.paths.modules', base_path('Modules'));

        if (! is_dir($modulesPath)) {
            return [];
        }

        $items = [];

        /** @var Finder $finder */
        $finder = Finder::create()
            ->in($modulesPath)
            ->depth('== 1')
            ->followLinks()
            ->files()
            ->name('module.json');

        foreach ($finder as $file) {
            $json = json_decode((string) $file->getContents(), true);
            if (! is_array($json)) {
                continue;
            }

            $name = (string) ($json['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $alias = (string) ($json['alias'] ?? '');
            $slug = $alias !== '' ? $alias : strtolower($name);

            $enabled = false;
            if (Module::has($name)) {
                $enabled = (bool) Module::find($name)?->isEnabled();
            }

            $items[] = [
                'slug' => $slug,
                'module_name' => $name,
                'name' => $name,
                'description' => (string) ($json['description'] ?? ''),
                'version' => '0.0.0',
                'author' => 'Local',
                'license' => '',
                'tags' => [],
                'compatibility' => [],
                'repository' => '',
                'download_url' => '',
                'cover' => null,
                'catalog_kind' => 'module',
                'is_local' => true,
                'installed' => true,
                'enabled' => $enabled,
            ];
        }

        usort($items, fn (array $a, array $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @param  array<int, array<string, mixed>>  $local
     * @return array<int, array<string, mixed>>
     */
    private static function mergeCatalogAndLocalModules(array $catalog, array $local): array
    {
        $catalogModuleNames = array_filter(array_map(fn ($row) => is_array($row) ? ($row['module_name'] ?? null) : null, $catalog));
        $catalogModuleNames = array_values(array_unique(array_map('strval', $catalogModuleNames)));

        foreach ($local as $row) {
            if (! is_array($row)) {
                continue;
            }

            $moduleName = (string) ($row['module_name'] ?? '');
            if ($moduleName === '' || in_array($moduleName, $catalogModuleNames, true)) {
                continue;
            }

            $catalog[] = $row;
        }

        return $catalog;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findLocalModuleBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        foreach (self::fetchLocalModules() as $row) {
            if (strtolower((string) ($row['slug'] ?? '')) === $slug) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchAllCatalogItems(): array
    {
        $extensions = [];

        try {
            $extensions = array_map(function (array $row) {
                $row['catalog_kind'] = 'module';

                return $row;
            }, ExtensionCatalog::make()->fetchExtensions());
        } catch (\Throwable $e) {
            report($e);
        }

        $templates = [];

        try {
            $templates = PdfTemplateCatalog::make()->fetchTemplates();
        } catch (\Throwable $e) {
            report($e);
        }

        $extensionSlugs = array_column($extensions, 'slug');

        foreach ($templates as $tpl) {
            if (in_array($tpl['slug'] ?? '', $extensionSlugs, true)) {
                continue;
            }

            $extensions[] = $tpl;
        }

        return $extensions;
    }

    public static function upload($request)
    {
        // Create temp directory
        $temp_dir = storage_path('app/temp-'.md5(mt_rand()));

        if (! File::isDirectory($temp_dir)) {
            File::makeDirectory($temp_dir);
        }

        $path = $request->file('avatar')->storeAs(
            'temp-'.md5(mt_rand()),
            $request->module.'.zip',
            'local'
        );

        return $path;
    }

    /**
     * @return array<string, mixed>|false
     */
    public static function download(string $module, string $version)
    {
        $items = self::fetchAllCatalogItems();

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'extensions_catalog_unavailable',
            ];
        }

        $extension = collect($items)->firstWhere('module_name', $module);

        if (! $extension) {
            return [
                'success' => false,
                'message' => 'module_not_found',
            ];
        }

        if (($extension['version'] ?? '') !== $version) {
            return [
                'success' => false,
                'message' => 'version_mismatch',
            ];
        }

        $downloadUrl = $extension['download_url'] ?? '';

        if ($downloadUrl === '') {
            return [
                'success' => false,
                'message' => 'download_url_missing',
            ];
        }

        $scheme = strtolower((string) parse_url((string) $downloadUrl, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return [
                'success' => false,
                'message' => 'download_url_invalid',
            ];
        }

        $path = null;

        // Create temp directory
        $tempDir = storage_path('app/temp-'.md5((string) mt_rand()));

        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir);
        }

        $zipFilePath = $tempDir.'/upload.zip';

        try {
            $response = Http::timeout(120)
                ->withOptions([
                    'allow_redirects' => true,
                    'sink' => $zipFilePath,
                ])
                ->get($downloadUrl);
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Download Exception',
                'data' => [
                    'path' => $path,
                ],
            ];
        }

        if (! $response->successful()) {
            File::delete($zipFilePath);

            return [
                'success' => false,
                'message' => 'download_failed',
                'status' => $response->status(),
            ];
        }

        $size = @filesize($zipFilePath);
        if (! is_int($size) || $size <= 0) {
            File::delete($zipFilePath);

            return [
                'success' => false,
                'message' => 'download_write_failed',
            ];
        }

        if ($size > self::MAX_PACKAGE_BYTES) {
            File::delete($zipFilePath);

            return [
                'success' => false,
                'message' => 'download_too_large',
                'max_bytes' => self::MAX_PACKAGE_BYTES,
                'size_bytes' => $size,
            ];
        }

        return [
            'success' => true,
            'path' => $zipFilePath,
        ];
    }

    public static function unzip($module, $zip_file_path)
    {
        if (! file_exists($zip_file_path)) {
            throw new \Exception('Zip file not found');
        }

        $temp_extract_dir = storage_path('app/temp2-'.md5(mt_rand()));

        if (! File::isDirectory($temp_extract_dir)) {
            File::makeDirectory($temp_extract_dir);
        }
        // Unzip the file
        $zip = new ZipArchive;

        $opened = $zip->open($zip_file_path);

        if ($opened !== true) {
            throw new \RuntimeException('Could not open ZIP (code '.$opened.'). The file may be corrupted or not a ZIP archive.');
        }

        self::safeExtractZip($zip, $temp_extract_dir);
        $zip->close();

        // Delete zip file
        File::delete($zip_file_path);

        return $temp_extract_dir;
    }

    public static function copyFiles($module, $temp_extract_dir, string $catalogKind = 'module')
    {
        if ($catalogKind === 'pdf_template') {
            return self::copyPdfTemplateFiles($module, $temp_extract_dir);
        }

        if (! File::isDirectory(base_path('Modules'))) {
            File::makeDirectory(base_path('Modules'));
        }

        $modulePath = base_path('Modules/'.$module);
        $normalizedModulePath = self::prepareNormalizedModuleDirectory($module, $temp_extract_dir);

        // Delete temp directory
        File::deleteDirectory($temp_extract_dir);

        if (File::isDirectory($modulePath)) {
            File::deleteDirectory($modulePath);
        }

        if (! File::copyDirectory($normalizedModulePath, $modulePath)) {
            throw new \RuntimeException('Could not copy module files into Modules/. Check permissions and disk space.');
        }

        File::deleteDirectory(dirname($normalizedModulePath));

        return true;
    }

    /**
     * Install PDF template files from an extracted ZIP into storage/app/templates/pdf/{invoice|estimate}/.
     */
    private static function copyPdfTemplateFiles(string $module, string $tempExtractDir): bool
    {
        $items = self::fetchAllCatalogItems();
        $entry = collect($items)->firstWhere('module_name', $module);

        if (! $entry || ($entry['catalog_kind'] ?? '') !== 'pdf_template') {
            throw new \RuntimeException('PDF template catalog entry not found for module name ['.$module.'].');
        }

        $type = (string) ($entry['pdf_template_type'] ?? '');
        $templateName = (string) ($entry['template_name'] ?? '');

        if (! in_array($type, ['invoice', 'estimate'], true) || $templateName === '') {
            throw new \RuntimeException('Invalid PDF template metadata.');
        }

        $bladePath = null;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempExtractDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($file->getFilename() === $templateName.'.blade.php') {
                $bladePath = $file->getPathname();
                break;
            }
        }

        if ($bladePath === null || ! is_file($bladePath)) {
            throw new \RuntimeException(
                'Could not find '.$templateName.'.blade.php inside the package. Check templates.json template_name matches the archive.'
            );
        }

        $disk = Storage::disk('pdf_templates');
        $bladeContents = File::get($bladePath);
        $disk->put($type.'/'.$templateName.'.blade.php', $bladeContents);

        $pngPath = dirname($bladePath).'/'.$templateName.'.png';
        if (is_file($pngPath)) {
            $disk->put($type.'/'.$templateName.'.png', File::get($pngPath));
        }

        File::deleteDirectory($tempExtractDir);

        return true;
    }

    public static function deleteFiles($json)
    {
        $files = json_decode($json);

        foreach ($files as $file) {
            File::delete(base_path($file));
        }

        return true;
    }

    public static function complete($module, $version, string $catalogKind = 'module')
    {
        if ($catalogKind === 'pdf_template') {
            $items = self::fetchAllCatalogItems();
            $entry = collect($items)->firstWhere('module_name', $module);

            if (! $entry || ($entry['catalog_kind'] ?? '') !== 'pdf_template') {
                throw new \RuntimeException('PDF template catalog entry not found for module name ['.$module.'].');
            }

            $slug = (string) ($entry['slug'] ?? '');
            if ($slug === '') {
                throw new \RuntimeException('PDF template slug is missing.');
            }

            $json = json_decode(Setting::getSetting('pdf_template_catalog_versions') ?: '{}', true);
            if (! is_array($json)) {
                $json = [];
            }
            $json[$slug] = $version;
            Setting::setSetting('pdf_template_catalog_versions', json_encode($json));

            return true;
        }

        self::normalizeModuleInstallLocation($module);

        self::clearModuleCache();

        try {
            $exitCode = Artisan::call("module:migrate {$module} --force");
        } catch (\Throwable $e) {
            throw new \RuntimeException('module:migrate: '.$e->getMessage(), 0, $e);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'module:migrate failed (exit '.$exitCode.').');
        }

        try {
            $exitCode = Artisan::call("module:seed {$module} --force");
        } catch (\Throwable $e) {
            throw new \RuntimeException('module:seed: '.$e->getMessage(), 0, $e);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'module:seed failed (exit '.$exitCode.').');
        }

        try {
            $exitCode = Artisan::call("module:enable {$module}");
        } catch (\Throwable $e) {
            throw new \RuntimeException('module:enable: '.$e->getMessage(), 0, $e);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'module:enable failed (exit '.$exitCode.').');
        }

        $module = ModelsModule::updateOrCreate(['name' => $module], ['version' => $version, 'installed' => true, 'enabled' => true]);

        ModuleInstalledEvent::dispatch($module);
        ModuleEnabledEvent::dispatch($module);

        return true;
    }

    /**
     * @return array{
     *   shell_commands: array<int, string>,
     *   runnable_commands: array<int, string>,
     *   notes: array<int, string>
     * }
     */
    public static function postInstallHints(string $moduleName, string $catalogKind = 'module'): array
    {
        if ($catalogKind === 'pdf_template') {
            return ['shell_commands' => [], 'runnable_commands' => [], 'notes' => []];
        }

        $shellCommands = [
            'composer dump-autoload',
        ];

        $runnable = [
            'module:dump --all',
            'optimize:clear',
        ];

        $modulePath = base_path('Modules/'.$moduleName);

        if (File::exists($modulePath.'/package.json') || File::exists($modulePath.'/vite.config.js')) {
            $shellCommands[] = 'npm run build';
        }

        $notes = [
            'Some extensions add PHP dependencies; you may need to run composer install during deployment.',
            'In Docker/immutable deployments, installing extensions at runtime may require a writable volume or rebuilding the image.',
        ];

        return [
            'shell_commands' => $shellCommands,
            'runnable_commands' => $runnable,
            'notes' => $notes,
        ];
    }

    /**
     * Run safe post-install Artisan commands (no composer/npm).
     *
     * @return array{success: bool, output: array<int, array{command: string, exit_code: int, output: string}>}
     */
    public static function runPostInstallCommands(string $moduleName, string $catalogKind = 'module'): array
    {
        if ($catalogKind === 'pdf_template') {
            return ['success' => true, 'output' => []];
        }

        $hints = self::postInstallHints($moduleName, $catalogKind);
        $commands = $hints['runnable_commands'] ?? [];

        $out = [];
        foreach ($commands as $cmd) {
            try {
                $exit = Artisan::call($cmd);
                $out[] = [
                    'command' => $cmd,
                    'exit_code' => (int) $exit,
                    'output' => (string) Artisan::output(),
                ];
            } catch (\Throwable $e) {
                report($e);
                $out[] = [
                    'command' => $cmd,
                    'exit_code' => 1,
                    'output' => $e->getMessage(),
                ];
            }
        }

        $success = collect($out)->every(fn (array $row) => $row['exit_code'] === 0);

        return ['success' => $success, 'output' => $out];
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public static function uninstall(string $moduleName): array
    {
        $moduleRecord = ModelsModule::where('name', $moduleName)->first();

        if (! $moduleRecord || ! $moduleRecord->installed) {
            return ['success' => false, 'message' => 'module_not_installed'];
        }

        try {
            if (Module::has($moduleName)) {
                $installedModule = Module::find($moduleName);
                $installedModule->disable();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            Artisan::call("module:migrate-rollback {$moduleName} --force");
        } catch (\Throwable $e) {
            report($e);
        }

        $modulePath = base_path('Modules/'.$moduleName);

        if (File::isDirectory($modulePath)) {
            File::deleteDirectory($modulePath);
        }

        $moduleRecord->delete();

        self::clearModuleCache();
        Module::register();

        return ['success' => true];
    }

    /**
     * nwidart/laravel-modules only discovers manifests one level under Modules (each folder must contain module.json).
     * Zip releases sometimes ship a flat tree (module.json at the Modules root) or a mismatched folder name; fix layout before migrate.
     *
     * @param  string  $moduleName  StudlyCase name from module.json (e.g. WhiteLabel)
     */
    public static function normalizeModuleInstallLocation(string $moduleName, ?string $basePath = null): void
    {
        $base = $basePath ?? base_path();
        $modulesRoot = $base.'/Modules';

        if (! File::isDirectory($modulesRoot)) {
            throw new \RuntimeException('Modules directory does not exist.');
        }

        $canonicalManifest = $modulesRoot.'/'.$moduleName.'/module.json';

        if (File::exists($canonicalManifest)) {
            return;
        }

        // Modules/WhiteLabel/WhiteLabel/module.json
        $nested = $modulesRoot.'/'.$moduleName.'/'.$moduleName.'/module.json';
        if (File::exists($nested)) {
            $inner = $modulesRoot.'/'.$moduleName.'/'.$moduleName;
            $outer = $modulesRoot.'/'.$moduleName;
            self::promoteDirectoryContents($inner, $outer);
            File::deleteDirectory($inner);

            return;
        }

        // Modules/module.json (flat zip)
        $rootManifest = $modulesRoot.'/module.json';
        if (File::exists($rootManifest)) {
            $json = json_decode(File::get($rootManifest), true);
            if (($json['name'] ?? '') !== $moduleName) {
                throw new \RuntimeException(
                    'Modules/module.json declares ['.($json['name'] ?? 'unknown')."] but expected [{$moduleName}]."
                );
            }
            self::moveFlatModuleIntoFolder($modulesRoot, $moduleName);

            return;
        }

        foreach (glob($modulesRoot.'/*/module.json') ?: [] as $manifest) {
            $json = json_decode(File::get($manifest), true);
            if (($json['name'] ?? '') !== $moduleName) {
                continue;
            }
            $dir = dirname($manifest);
            if ($dir === $modulesRoot.'/'.$moduleName) {
                return;
            }
            if (File::exists($modulesRoot.'/'.$moduleName)) {
                File::deleteDirectory($modulesRoot.'/'.$moduleName);
            }
            File::moveDirectory($dir, $modulesRoot.'/'.$moduleName);

            return;
        }

        foreach (glob($modulesRoot.'/*/*/module.json') ?: [] as $manifest) {
            $json = json_decode(File::get($manifest), true);
            if (($json['name'] ?? '') !== $moduleName) {
                continue;
            }
            $dir = dirname($manifest);
            if ($dir === $modulesRoot.'/'.$moduleName) {
                return;
            }
            if (File::exists($modulesRoot.'/'.$moduleName)) {
                File::deleteDirectory($modulesRoot.'/'.$moduleName);
            }
            File::moveDirectory($dir, $modulesRoot.'/'.$moduleName);

            return;
        }

        throw new \RuntimeException(
            "Could not locate module.json for [{$moduleName}] under Modules/. Expected Modules/{$moduleName}/module.json or a compatible layout."
        );
    }

    private static function promoteDirectoryContents(string $source, string $destination): void
    {
        foreach (File::files($source) as $file) {
            $target = $destination.'/'.$file->getFilename();
            if (File::exists($target)) {
                File::delete($target);
            }
            File::move($file->getRealPath(), $target);
        }
        foreach (File::directories($source) as $dir) {
            $base = basename($dir);
            $target = $destination.'/'.$base;
            if (File::isDirectory($target)) {
                File::deleteDirectory($target);
            }
            File::moveDirectory($dir, $target);
        }
    }

    private static function moveFlatModuleIntoFolder(string $modulesRoot, string $moduleName): void
    {
        $target = $modulesRoot.'/'.$moduleName;
        if (File::isDirectory($target)) {
            File::deleteDirectory($target);
        }
        File::makeDirectory($target);

        foreach (File::files($modulesRoot) as $file) {
            $name = $file->getFilename();
            if (in_array($name, ['.gitignore', '.DS_Store'], true)) {
                continue;
            }
            File::move($file->getRealPath(), $target.'/'.$name);
        }

        foreach (File::directories($modulesRoot) as $dir) {
            $base = basename($dir);
            if ($base === $moduleName) {
                continue;
            }
            if (File::exists($dir.'/module.json')) {
                $json = json_decode(File::get($dir.'/module.json'), true);
                if (($json['name'] ?? '') !== $moduleName) {
                    continue;
                }
            }
            File::moveDirectory($dir, $target.'/'.$base);
        }
    }

    private static function safeExtractZip(ZipArchive $zip, string $destination): void
    {
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);
        $destinationReal = realpath($destination) ?: $destination;

        if ($zip->numFiles > self::MAX_EXTRACTED_FILES) {
            throw new \RuntimeException('ZIP contains too many files to be safely extracted.');
        }

        $totalBytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $name = str_replace('\\', '/', $name);

            if ($name === '' || str_contains($name, "\0")) {
                throw new \RuntimeException('Invalid ZIP entry name.');
            }

            if (str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:\\//', $name) === 1) {
                throw new \RuntimeException('ZIP contains an unsafe absolute path.');
            }

            $segments = array_values(array_filter(explode('/', $name), fn (string $s) => $s !== ''));
            foreach ($segments as $seg) {
                if ($seg === '.' || $seg === '..') {
                    throw new \RuntimeException('ZIP contains a path traversal entry.');
                }
            }

            $targetPath = $destination.'/'.implode('/', $segments);

            if (str_ends_with($name, '/')) {
                File::ensureDirectoryExists($targetPath);

                continue;
            }

            $stat = $zip->statIndex($i);
            if (is_array($stat) && isset($stat['size']) && is_numeric($stat['size'])) {
                $totalBytes += (int) $stat['size'];
                if ($totalBytes > self::MAX_EXTRACTED_BYTES) {
                    throw new \RuntimeException('ZIP is too large to be safely extracted.');
                }
            }

            File::ensureDirectoryExists(dirname($targetPath));

            $stream = $zip->getStream($zip->getNameIndex($i));
            if (! is_resource($stream)) {
                throw new \RuntimeException('Could not read ZIP entry stream.');
            }

            $out = fopen($targetPath, 'wb');
            if ($out === false) {
                fclose($stream);
                throw new \RuntimeException('Could not write ZIP entry to disk.');
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            $writtenReal = realpath($targetPath) ?: $targetPath;
            if (! str_starts_with($writtenReal, $destinationReal.DIRECTORY_SEPARATOR) && $writtenReal !== $destinationReal) {
                throw new \RuntimeException('ZIP extraction attempted to write outside destination.');
            }
        }
    }

    private static function prepareNormalizedModuleDirectory(string $moduleName, string $tempExtractDir): string
    {
        $stageBase = storage_path('app/module-stage-'.md5((string) mt_rand()));
        $stageModules = $stageBase.'/Modules';

        File::makeDirectory($stageModules, 0755, true, true);

        if (! File::copyDirectory($tempExtractDir, $stageModules)) {
            throw new \RuntimeException('Could not stage extracted files for normalization.');
        }

        self::normalizeModuleInstallLocation($moduleName, $stageBase);

        $normalized = $stageModules.'/'.$moduleName;
        if (! File::isDirectory($normalized) || ! File::exists($normalized.'/module.json')) {
            throw new \RuntimeException('Normalized module directory is missing module.json.');
        }

        return $normalized;
    }

    private static function clearModuleCache(): void
    {
        Cache::forget((string) config('modules.cache.key', 'laravel-modules'));

        try {
            Artisan::call('module:dump --all');
        } catch (\Throwable) {
            // Ignore: command may not be available in all environments.
        }
    }
}
