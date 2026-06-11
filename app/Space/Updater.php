<?php

namespace App\Space;

use App\Events\UpdateFinished;
use App\Models\Setting;
use Artisan;
use File;
use GuzzleHttp\Exception\RequestException;
use ZipArchive;

// Implementation taken from Akaunting - https://github.com/akaunting/akaunting
class Updater
{
    use SiteApi;

    public static function checkForUpdate($installed_version, $updater_channel = 'stable')
    {
        $data = null;
        $url = sprintf('releases/update-check/%s?channel=%s', $installed_version, $updater_channel);

        $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true]);

        $data = (object) ['success' => false, 'release' => null];
        if ($response && ($response->getStatusCode() == 200)) {
            $data = $response->getBody()->getContents();
            $data = json_decode($data);
        }

        if ($data->success && $data->release && property_exists($data->release, 'extensions')) {
            $extensions = [];
            foreach ($data->release->extensions as $extension) {
                $extensions[$extension] = phpversion($extension) !== false;
            }
            $extensions['php'.'('.$data->release->min_php_version.')'] = version_compare(phpversion(), $data->release->min_php_version, '>=');
            $data->release->extensions = $extensions;
        }

        return $data;
    }

    public static function download($new_version, $is_cmd = 0)
    {
        $data = null;
        $path = null;

        $url = 'releases/download/'.$new_version.'.zip';
        $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true]);

        // Exception
        if ($response instanceof RequestException) {
            return [
                'success' => false,
                'error' => 'Download Exception',
                'data' => [
                    'path' => $path,
                ],
            ];
        }

        if ($response && ($response->getStatusCode() == 200)) {
            $data = $response->getBody()->getContents();
        }

        // Create temp directory
        $temp_dir = storage_path('app/temp-'.md5(mt_rand()));

        if (! File::isDirectory($temp_dir)) {
            File::makeDirectory($temp_dir);
        }

        $zip_file_path = $temp_dir.'/upload.zip';

        // Add content to the Zip file
        $uploaded = is_int(file_put_contents($zip_file_path, $data)) ? true : false;

        if (! $uploaded) {
            return false;
        }

        return $zip_file_path;
    }

    public static function unzip($zip_file_path)
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

        if ($zip->open($zip_file_path)) {
            $zip->extractTo($temp_extract_dir);
        }

        $zip->close();

        // Delete zip file
        File::delete($zip_file_path);

        return $temp_extract_dir;
    }

    public static function copyFiles($temp_extract_dir)
    {
        if (! File::copyDirectory($temp_extract_dir.'/InvoiceShelf', base_path())) {
            return false;
        }

        // Clear stale compiled caches from the previous version so the newly copied
        // release boots cleanly. This runs as the currently-installed code — the last
        // reliable point before the new release boots — and is critical for major
        // upgrades (e.g. v2 -> v3) where cached config/routes/package discovery differ.
        static::clearCompiledCaches();

        // Delete temp directory
        File::deleteDirectory($temp_extract_dir);

        return true;
    }

    /**
     * Remove files left behind by the previous version that are not part of the new
     * release. The new release ships a manifest.json listing every file it contains;
     * anything under base_path() not in that manifest (and not protected) is deleted.
     * No manifest present (same-line update) is a safe no-op.
     */
    public static function cleanStaleFiles(?string $basePath = null): array
    {
        $basePath = $basePath ?? base_path();
        $manifestPath = $basePath.'/manifest.json';

        if (! File::exists($manifestPath)) {
            return ['success' => true, 'cleaned' => 0];
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest)) {
            return ['success' => false, 'error' => 'Invalid manifest'];
        }

        $manifestLookup = array_flip($manifest);
        $protectedPaths = config('invoiceshelf.update_protected_paths', []);
        $cleaned = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = substr($file->getPathname(), strlen($basePath) + 1);

            if (static::isProtectedPath($relativePath, $protectedPaths)) {
                continue;
            }

            if ($file->isFile() && ! isset($manifestLookup[$relativePath])) {
                File::delete($file->getPathname());
                $cleaned++;
            }
        }

        // Second pass: remove now-empty directories.
        $dirIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($dirIterator as $item) {
            if (! $item->isDir()) {
                continue;
            }

            $relativePath = substr($item->getPathname(), strlen($basePath) + 1);

            if (static::isProtectedPath($relativePath, $protectedPaths)) {
                continue;
            }

            $entries = scandir($item->getPathname());

            if (count($entries) <= 2) {
                @rmdir($item->getPathname());
            }
        }

        return ['success' => true, 'cleaned' => $cleaned];
    }

    /**
     * Delete compiled config/route/event/package caches and compiled views so the
     * freshly copied release re-reads config and re-runs package discovery on boot.
     * bootstrap/cache is a protected path for cleanStaleFiles(), so it is cleared here.
     */
    public static function clearCompiledCaches(): void
    {
        foreach (File::glob(base_path('bootstrap/cache/*.php')) as $file) {
            File::delete($file);
        }

        $compiledViews = storage_path('framework/views');

        if (File::isDirectory($compiledViews)) {
            foreach (File::glob($compiledViews.'/*.php') as $file) {
                File::delete($file);
            }
        }
    }

    private static function isProtectedPath(string $relativePath, array $protectedPaths): bool
    {
        foreach ($protectedPaths as $protected) {
            if ($relativePath === $protected || str_starts_with($relativePath, $protected.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function deleteFiles($json)
    {
        $files = json_decode($json);

        foreach ($files as $file) {
            File::delete(base_path($file));
        }

        return true;
    }

    public static function migrateUpdate()
    {
        Artisan::call('migrate --force');

        return true;
    }

    public static function finishUpdate($installed, $version)
    {
        Setting::setSetting('version', $version);
        event(new UpdateFinished($installed, $version));

        return [
            'success' => true,
            'error' => false,
            'data' => [],
        ];
    }
}
