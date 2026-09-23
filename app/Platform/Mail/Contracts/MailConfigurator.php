<?php

namespace App\Platform\Mail\Contracts;

interface MailConfigurator
{
    public function applyGlobalConfig(): void;

    public function applyCompanyConfig(int|string $companyId): void;

    /**
     * The company settings that hold a company's mail transport, credentials
     * among them. Only the owner reads them, through the mail configuration
     * endpoints.
     *
     * @return list<string>
     */
    public function getCompanySettingKeys(): array;
}
