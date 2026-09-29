<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Models\User;
use App\Platform\Notifications\NotificationCatalogue;

/**
 * Where each person wants each kind of notice: in the bell, by email, both
 * or neither. Kept in the user's settings as one JSON entry, holding only
 * the choices they changed; anything missing takes the catalogue's default.
 */
class NotificationPreferences
{
    public const SETTING = 'notification_preferences';

    public function __construct(private readonly NotificationCatalogue $catalogue) {}

    /**
     * Every type, with the person's choice or the default.
     *
     * @return array<string, array{bell: bool, mail: bool}>
     */
    public function for(User $user): array
    {
        $stored = $this->stored($user);
        $preferences = [];

        foreach ($this->catalogue->all() as $key => $type) {
            $preferences[$key] = [
                'bell' => (bool) ($stored[$key]['bell'] ?? true),
                'mail' => (bool) ($stored[$key]['mail'] ?? $type->mail),
            ];
        }

        return $preferences;
    }

    /**
     * The channels a notice of this type reaches the person on.
     *
     * @return list<string>
     */
    public function channels(User $user, string $type): array
    {
        $choice = $this->for($user)[$type] ?? ['bell' => true, 'mail' => false];
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
     * Save the given choices over the stored ones. Types the catalogue does
     * not know are dropped.
     *
     * @param  array<string, array{bell?: bool, mail?: bool}>  $choices
     */
    public function update(User $user, array $choices): void
    {
        $stored = $this->stored($user);

        foreach ($choices as $key => $choice) {
            if (! $this->catalogue->has((string) $key)) {
                continue;
            }

            foreach (['bell', 'mail'] as $channel) {
                if (array_key_exists($channel, $choice)) {
                    $stored[$key][$channel] = (bool) $choice[$channel];
                }
            }
        }

        $user->setSettings([self::SETTING => json_encode($stored)]);
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
