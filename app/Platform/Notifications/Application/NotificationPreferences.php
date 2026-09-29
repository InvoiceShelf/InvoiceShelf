<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Models\User;
use App\Platform\Notifications\NotificationCatalogue;

/**
 * Where each person wants each kind of notice: in the bell, by email, both
 * or neither. Kept in the user's settings as one JSON entry, holding only
 * the choices they changed.
 *
 * What reaches someone in a company is settled in this order: a type the
 * company switched off reaches nobody; otherwise the person's own choice;
 * otherwise the company's default; otherwise the catalogue's.
 */
class NotificationPreferences
{
    public const SETTING = 'notification_preferences';

    public function __construct(
        private readonly NotificationCatalogue $catalogue,
        private readonly CompanyNotificationDefaults $companyDefaults,
    ) {}

    /**
     * Every type as it applies to the person in the given company, with
     * which channels are their own choice rather than a default.
     *
     * @return array<string, array{enabled: bool, bell: bool, mail: bool, customised: array{bell: bool, mail: bool}}>
     */
    public function for(User $user, ?int $companyId = null): array
    {
        $stored = $this->stored($user);
        $company = $this->companyDefaults->for($companyId);
        $preferences = [];

        foreach (array_keys($this->catalogue->all()) as $key) {
            $preferences[$key] = [
                'enabled' => $company[$key]['enabled'],
                'bell' => (bool) ($stored[$key]['bell'] ?? $company[$key]['bell']),
                'mail' => (bool) ($stored[$key]['mail'] ?? $company[$key]['mail']),
                'customised' => [
                    'bell' => isset($stored[$key]['bell']),
                    'mail' => isset($stored[$key]['mail']),
                ],
            ];
        }

        return $preferences;
    }

    /**
     * The channels a notice of this type reaches the person on, in the
     * given company (none for a platform notice).
     *
     * @return list<string>
     */
    public function channels(User $user, string $type, ?int $companyId = null): array
    {
        $choice = $this->for($user, $companyId)[$type] ?? null;

        if ($choice === null || ! $choice['enabled']) {
            return [];
        }

        $channels = [];

        if ($choice['bell']) {
            $channels[] = 'database';
        }

        if ($choice['mail'] && $user->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Save the given choices over the stored ones. A null choice forgets
     * the person's own, so the company's default applies again. Types the
     * catalogue does not know are dropped.
     *
     * @param  array<string, array{bell?: bool|null, mail?: bool|null}>  $choices
     */
    public function update(User $user, array $choices): void
    {
        $stored = $this->stored($user);

        foreach ($choices as $key => $choice) {
            if (! $this->catalogue->has((string) $key)) {
                continue;
            }

            foreach (['bell', 'mail'] as $channel) {
                if (! array_key_exists($channel, $choice)) {
                    continue;
                }

                if ($choice[$channel] === null) {
                    unset($stored[$key][$channel]);
                } else {
                    $stored[$key][$channel] = (bool) $choice[$channel];
                }
            }

            if (($stored[$key] ?? []) === []) {
                unset($stored[$key]);
            }
        }

        $user->setSettings([self::SETTING => json_encode((object) $stored)]);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function stored(User $user): array
    {
        $raw = $user->getSettings([self::SETTING])->get(self::SETTING);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_filter($decoded, 'is_array') : [];
    }
}
