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

    /**
     * The sender the send dialogs prefill: the configured from name and
     * address.
     *
     * @return array{from_name: mixed, from_mail: mixed}
     */
    public function getDefaultConfig(): array;
}
