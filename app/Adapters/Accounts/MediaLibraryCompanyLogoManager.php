<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\CompanyLogoManager;
use App\Domains\Accounts\Models\Company;
use App\Support\Media\SafeFileName;

class MediaLibraryCompanyLogoManager implements CompanyLogoManager
{
    private const COLLECTION = 'logo';

    public function clear(Company $company): void
    {
        $company->clearMediaCollection(self::COLLECTION);
    }

    public function replaceBase64(Company $company, string $contents, string $fileName): void
    {
        $this->clear($company);
        $company->addMediaFromBase64($contents)
            ->usingFileName(SafeFileName::from($fileName))
            ->toMediaCollection(self::COLLECTION);
    }
}
