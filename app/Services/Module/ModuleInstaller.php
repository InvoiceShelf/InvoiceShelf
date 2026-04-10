<?php

namespace App\Services\Module;

use App\Events\ModuleEnabledEvent;
use App\Events\ModuleInstalledEvent;
use App\Models\Module as ModelsModule;
use App\Models\Setting;
use App\Traits\SiteApi;
use Artisan;
use File;
use GuzzleHttp\Exception\RequestException;
use Nwidart\Modules\Facades\Module;
use ZipArchive;

// Implementation taken from Akaunting - https://github.com/akaunting/akaunting
class ModuleInstaller
{
    use SiteApi;

    private static function marketplaceToken(): ?string
    {
        $token = Setting::getSetting('api_token');

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return $token;
    }

    private static function decodeMarketplaceJson($response): array
    {
        if ($response instanceof RequestException || ! $response) {
            return [
                'status' => 0,
                'body' => null,
            ];
        }

        $body = $response->getBody()->getContents();

        return [
            'status' => $response->getStatusCode(),
            'body' => $body !== '' ? json_decode($body) : null,
        ];
    }

    public static function getModules(): array
    {
        $url = env('APP_ENV') === 'development'
            ? 'api/marketplace/modules?is_dev=1'
            : 'api/marketplace/modules';

        $token = static::marketplaceToken();
        $decoded = static::decodeMarketplaceJson(
            static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], $token)
        );

        if ($decoded['status'] === 401 && $token !== null) {
            $decoded = static::decodeMarketplaceJson(
                static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], null)
            );
        }

        return $decoded;
    }

    public static function getModule(string $module): array
    {
        $url = env('APP_ENV') === 'development'
            ? 'api/marketplace/modules/'.$module.'?is_dev=1'
            : 'api/marketplace/modules/'.$module;

        $token = static::marketplaceToken();
        $decoded = static::decodeMarketplaceJson(
            static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], $token)
        );

        if ($decoded['status'] === 401 && $token !== null) {
            $decoded = static::decodeMarketplaceJson(
                static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], null)
            );
        }

        return $decoded;
    }

    public static function upload($request): string
    {
        $tempDir = storage_path('app/temp-'.md5(mt_rand()));

        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir);
        }

        return $request->file('avatar')->storeAs(
            'temp-'.md5(mt_rand()),
            $request->module.'.zip',
            'local'
        );
    }

    public static function download(string $slug, string $version, ?string $checksumSha256 = null): array|bool
    {
        $data = null;
        $path = null;

        $url = env('APP_ENV') === 'development'
            ? "api/marketplace/modules/file/{$slug}?version={$version}&is_dev=1"
            : "api/marketplace/modules/file/{$slug}?version={$version}";

        $token = static::marketplaceToken();
        $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], $token);

        if ($response instanceof RequestException) {
            return [
                'success' => false,
                'error' => 'Download Exception',
                'data' => [
                    'path' => $path,
                ],
            ];
        }

        if ($response && $response->getStatusCode() === 401 && $token !== null) {
            $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], null);
        }

        if ($response instanceof RequestException || ! $response) {
            return [
                'success' => false,
                'error' => 'Download Exception',
            ];
        }

        if ($response && $response->getStatusCode() !== 200) {
            $decoded = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => false,
                'error' => $decoded['error'] ?? 'Module download failed',
            ];
        }

        if ($response && $response->getStatusCode() === 200) {
            $data = $response->getBody()->getContents();
        }

        $tempDir = storage_path('app/temp-'.md5(mt_rand()));

        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir);
        }

        $zipFilePath = $tempDir.'/upload.zip';
        $uploaded = is_int(file_put_contents($zipFilePath, $data));

        if (! $uploaded) {
            return false;
        }

        if ($checksumSha256 && hash_file('sha256', $zipFilePath) !== $checksumSha256) {
            File::delete($zipFilePath);

            return [
                'success' => false,
                'error' => 'Checksum verification failed',
            ];
        }

        return [
            'success' => true,
            'path' => $zipFilePath,
        ];
    }

    public static function unzip($module, $zipFilePath): string
    {
        if (! file_exists($zipFilePath)) {
            throw new \Exception('Zip file not found');
        }

        $tempExtractDir = storage_path('app/temp2-'.md5(mt_rand()));

        if (! File::isDirectory($tempExtractDir)) {
            File::makeDirectory($tempExtractDir);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipFilePath)) {
            $zip->extractTo($tempExtractDir);
        }

        $zip->close();
        File::delete($zipFilePath);

        return $tempExtractDir;
    }

    public static function copyFiles($module, $tempExtractDir): bool
    {
        if (! File::isDirectory(base_path('Modules'))) {
            File::makeDirectory(base_path('Modules'));
        }

        if (File::isDirectory(base_path('Modules').'/'.$module)) {
            File::deleteDirectory(base_path('Modules').'/'.$module);
        }

        if (! File::copyDirectory($tempExtractDir, base_path('Modules').'/')) {
            return false;
        }

        File::deleteDirectory($tempExtractDir);

        return true;
    }

    public static function deleteFiles($json): bool
    {
        $files = json_decode($json);

        foreach ($files as $file) {
            File::delete(base_path($file));
        }

        return true;
    }

    public static function complete($module, $version): bool
    {
        Module::register();

        Artisan::call("module:migrate $module --force");
        Artisan::call("module:seed $module --force");
        Artisan::call("module:enable $module");

        $module = ModelsModule::updateOrCreate(
            ['name' => $module],
            ['version' => $version, 'installed' => true, 'enabled' => true]
        );

        ModuleInstalledEvent::dispatch($module);
        ModuleEnabledEvent::dispatch($module);

        return true;
    }

    public static function checkToken(string $token)
    {
        $url = 'api/marketplace/ping';
        $normalizedToken = trim($token) !== '' ? $token : null;
        $response = static::getRemote($url, ['timeout' => 100, 'track_redirects' => true], $normalizedToken);

        if ($response && $response->getStatusCode() === 200) {
            return response()->json(json_decode($response->getBody()->getContents()));
        }

        return response()->json(['error' => 'invalid_token']);
    }
}
