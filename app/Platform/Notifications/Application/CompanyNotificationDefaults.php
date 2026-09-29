<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Notifications\NotificationCatalogue;

/**
 * What a company's owner decided for everyone in it: a kind of notice can be
 * switched off for the whole company, and each one can start in the bell,
 * by email or both for anyone who has not chosen for themselves.
 *
 * Kept as one company setting holding only what the owner changed; anything
 * missing takes the catalogue's default.
 */
class CompanyNotificationDefaults
{
    public const SETTING = 'notification_defaults';

    public function __construct(private readonly NotificationCatalogue $catalogue) {}

    /**
     * Every type, with the company's choice or the catalogue's.
     *
     * @return array<string, array{enabled: bool, bell: bool, mail: bool}>
     */
    public function for(?int $companyId): array
    {
        $stored = $companyId ? $this->stored($companyId) : [];
        $defaults = [];

        foreach ($this->catalogue->all() as $key => $type) {
            $defaults[$key] = [
                'enabled' => (bool) ($stored[$key]['enabled'] ?? true),
                'bell' => (bool) ($stored[$key]['bell'] ?? $type->bell),
                'mail' => (bool) ($stored[$key]['mail'] ?? $type->mail),
            ];
        }

        return $defaults;
    }

    /**
     * Save the given choices over the stored ones. Types the catalogue does
     * not know, and platform types, which no company owns, are dropped.
     *
     * @param  array<string, array{enabled?: bool, bell?: bool, mail?: bool}>  $choices
     */
    public function update(int $companyId, array $choices): void
    {
        $stored = $this->stored($companyId);

        foreach ($choices as $key => $choice) {
            if (! $this->catalogue->has((string) $key) || $this->catalogue->get((string) $key)->platform) {
                continue;
            }

            foreach (['enabled', 'bell', 'mail'] as $field) {
                if (array_key_exists($field, $choice)) {
                    $stored[$key][$field] = (bool) $choice[$field];
                }
            }
        }

        CompanySetting::setSettings([self::SETTING => json_encode($stored)], $companyId);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function stored(int $companyId): array
    {
        $raw = CompanySetting::getSetting(self::SETTING, $companyId);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_filter($decoded, 'is_array') : [];
    }
}
