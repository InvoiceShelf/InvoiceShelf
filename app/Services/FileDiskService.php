<?php

namespace App\Services;

use App\Models\FileDisk;
use Illuminate\Http\Request;

class FileDiskService
{
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
            return $disk->name === 'local_public' ? 'local_public' : 'local';
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

        $credentials = $disk->getDecodedCredentials();
        $baseConfig = config('filesystems.disks.'.$disk->driver, []);

        foreach ($baseConfig as $key => $value) {
            if ($credentials->has($key)) {
                $baseConfig[$key] = $credentials[$key];
            }
        }

        // Resolve relative local roots to storage/app/{path}
        if ($disk->driver === 'local' && isset($baseConfig['root']) && ! str_starts_with($baseConfig['root'], '/')) {
            $baseConfig['root'] = storage_path('app/'.$baseConfig['root']);
        }

        config(['filesystems.disks.'.$diskName => $baseConfig]);

        return $diskName;
    }

    public function validateCredentials(array $credentials, string $driver): bool
    {
        // Create a temporary disk config for validation
        $baseConfig = config('filesystems.disks.'.$driver, []);

        foreach ($baseConfig as $key => $value) {
            if (isset($credentials[$key])) {
                $baseConfig[$key] = $credentials[$key];
            }
        }

        if ($driver === 'local' && isset($baseConfig['root']) && ! str_starts_with($baseConfig['root'], '/')) {
            $baseConfig['root'] = storage_path('app/'.$baseConfig['root']);
        }

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
