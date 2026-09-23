<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\UserAvatarManager;
use App\Domains\Accounts\Models\User;
use App\Support\Media\SafeFileName;

class MediaLibraryUserAvatarManager implements UserAvatarManager
{
    private const COLLECTION = 'admin_avatar';

    public function clear(User $user): void
    {
        $user->clearMediaCollection(self::COLLECTION);
    }

    public function replaceFile(User $user, string $path, string $fileName): void
    {
        $this->clear($user);
        $user->addMedia($path)
            ->usingFileName(SafeFileName::from($fileName))
            ->toMediaCollection(self::COLLECTION);
    }

    public function replaceBase64(User $user, string $contents, string $fileName): void
    {
        $this->clear($user);
        $user->addMediaFromBase64($contents)
            ->usingFileName(SafeFileName::from($fileName))
            ->toMediaCollection(self::COLLECTION);
    }
}
