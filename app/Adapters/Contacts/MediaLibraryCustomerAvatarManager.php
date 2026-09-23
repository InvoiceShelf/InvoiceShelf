<?php

namespace App\Adapters\Contacts;

use App\Domains\Contacts\Contracts\CustomerAvatarManager;
use App\Domains\Contacts\Models\Customer;
use App\Support\Media\SafeFileName;

class MediaLibraryCustomerAvatarManager implements CustomerAvatarManager
{
    private const COLLECTION = 'customer_avatar';

    public function clear(Customer $customer): void
    {
        $customer->clearMediaCollection(self::COLLECTION);
    }

    public function replace(Customer $customer, string $path, string $fileName): void
    {
        $this->clear($customer);
        $customer->addMedia($path)
            ->usingFileName(SafeFileName::from($fileName))
            ->toMediaCollection(self::COLLECTION);
    }
}
