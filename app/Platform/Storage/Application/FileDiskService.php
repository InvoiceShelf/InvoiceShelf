<?php

namespace App\Platform\Storage\Application;

use App\Platform\Storage\Contracts\StorageConfigurator;
use App\Platform\Storage\Models\FileDisk;
use App\Support\Net\PrivateNetworkGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileDiskService implements StorageConfigurator
{
    public function applyGlobalConfig(): void
    {
        $fileDisk = FileDisk::whereSetAsDefault(true)->first();

        if (! $fileDisk) {
            return;
        }

        $diskName = $this->registerDisk($fileDisk);

        config(['media-library.disk_name' => $diskName]);
    }

    public function create(Request $request): FileDisk
    {
        if ($request->set_as_default) {
            $this->clearDefaults();
        }

        $credentials = $this->normalizeCredentials($request->credentials, $request->driver);

        return FileDisk::create([
            'credentials' => $credentials,
            'name' => $request->name,
            'driver' => $request->driver,
            'set_as_default' => $request->set_as_default,
            'company_id' => $request->header('company'),
        ]);
    }

    public function update(FileDisk $disk, Request $request): FileDisk
    {
        $credentials = $this->normalizeCredentials($request->credentials, $request->driver);

        $data = [
            'credentials' => $credentials,
            'name' => $request->name,
            'driver' => $request->driver,
        ];

        if (! $disk->set_as_default) {
            if ($request->set_as_default) {
                $this->clearDefaults();
            }

            $data['set_as_default'] = $request->set_as_default;
        }

        $disk->update($data);

        return $disk;
    }

    public function setAsDefault(FileDisk $disk): FileDisk
    {
        $this->clearDefaults();

        $disk->set_as_default = true;
        $disk->save();

        return $disk;
    }

    /**
     * Get the unique Laravel filesystem disk name for a FileDisk.
     */
    public function getDiskName(FileDisk $disk): string
    {
        if ($disk->isSystem()) {
            return $disk->name === 'public' ? 'public' : 'local';
        }

        return 'disk_'.$disk->id;
    }

    /**
     * Register a FileDisk in the runtime filesystem configuration.
     * Returns the Laravel disk name. Does NOT change filesystems.default.
     */
    public function registerDisk(FileDisk $disk): string
    {
        $diskName = $this->getDiskName($disk);

        // System disks are already in config/filesystems.php
        if ($disk->isSystem()) {
            return $diskName;
        }

        if (! in_array($disk->driver, FileDisk::DRIVERS, true)) {
            Log::warning('File disk has a driver outside the allowlist; using the local disk instead.', [
                'file_disk_id' => $disk->id,
                'driver' => $disk->driver,
            ]);

            return 'local';
        }

        $baseConfig = $this->buildConfig($disk->getDecodedCredentials()->all(), $disk->driver);

        config(['filesystems.disks.'.$diskName => $baseConfig]);

        return $diskName;
    }

    public function validateCredentials(array $credentials, string $driver): bool
    {
        // SSRF guard: reject S3/Spaces (or any) endpoints that resolve to a
        // private or reserved host before we make a live request to them.
        if (isset($credentials['endpoint'])
            && is_string($credentials['endpoint'])
            && $credentials['endpoint'] !== ''
            && PrivateNetworkGuard::blockedReason($credentials['endpoint']) !== null) {
            return false;
        }

        if (! in_array($driver, FileDisk::DRIVERS, true)) {
            return false;
        }

        // Create a temporary disk config for validation
        $baseConfig = $this->buildConfig($credentials, $driver);

        $tempDiskName = 'validation_temp';
        config(['filesystems.disks.'.$tempDiskName => $baseConfig]);

        try {
            $root = '';
            if ($driver == 'dropbox') {
                $root = $credentials['root'].'/';
            }
            \Storage::disk($tempDiskName)->put($root.'invoiceshelf_temp.text', 'Check Credentials');

            if (\Storage::disk($tempDiskName)->exists($root.'invoiceshelf_temp.text')) {
                \Storage::disk($tempDiskName)->delete($root.'invoiceshelf_temp.text');

                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * The runtime config for a driver: its base entry in config/filesystems.php
     * with the stored credentials laid over the keys it already has.
     *
     * The driver itself is never taken from the credentials, so a row saved as
     * one driver cannot turn into another at runtime. Relative local roots
     * resolve under storage/app.
     */
    private function buildConfig(array $credentials, string $driver): array
    {
        $config = config('filesystems.disks.'.$driver, []);

        foreach ($config as $key => $value) {
            if ($key !== 'driver' && array_key_exists($key, $credentials)) {
                $config[$key] = $credentials[$key];
            }
        }

        if ($driver === 'local' && isset($config['root']) && ! str_starts_with($config['root'], '/')) {
            $config['root'] = storage_path('app/'.$config['root']);
        }

        return $config;
    }

    /**
     * For local disks, strip any absolute prefix and store the root
     * as a path relative to storage/app. At runtime the path is
     * resolved to an absolute path via storage_path().
     */
    private function normalizeCredentials(array $credentials, string $driver): array
    {
        if ($driver === 'local' && isset($credentials['root'])) {
            $root = $credentials['root'];

            $storageApp = storage_path('app').'/';
            if (str_starts_with($root, $storageApp)) {
                $root = substr($root, strlen($storageApp));
            }

            $root = ltrim($root, '/');

            $credentials['root'] = $root;
        }

        return $credentials;
    }

    private function clearDefaults(): void
    {
        FileDisk::query()->update(['set_as_default' => false]);
    }
}
