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

        return FileDisk::create([
            'credentials' => $request->credentials,
            'name' => $request->name,
            'driver' => $request->driver,
            'set_as_default' => $request->set_as_default,
            'company_id' => $request->header('company'),
        ]);
    }

    public function update(FileDisk $disk, Request $request): FileDisk
    {
        $data = [
            'credentials' => $request->credentials,
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

    public function validateCredentials(array $credentials, string $driver): bool
    {
        FileDisk::setFilesystem(collect($credentials), $driver);

        $prefix = env('DYNAMIC_DISK_PREFIX', 'temp_');

        try {
            $root = '';
            if ($driver == 'dropbox') {
                $root = $credentials['root'].'/';
            }
            \Storage::disk($prefix.$driver)->put($root.'invoiceshelf_temp.text', 'Check Credentials');

            if (\Storage::disk($prefix.$driver)->exists($root.'invoiceshelf_temp.text')) {
                \Storage::disk($prefix.$driver)->delete($root.'invoiceshelf_temp.text');

                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    private function clearDefaults(): void
    {
        FileDisk::query()->update(['set_as_default' => false]);
    }
}
