<?php

namespace App\Services;

class CompanyMailConfigService
{
    public static function apply(int $companyId): void
    {
        app(MailConfigurationService::class)->applyCompanyConfig($companyId);
    }
}
