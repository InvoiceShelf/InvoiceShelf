<?php

namespace App\Platform\Notifications;

/**
 * One kind of notice the app sends staff: who gets it, where it is listed in
 * the preferences, and whether it is emailed unless the recipient says
 * otherwise.
 *
 * A type with an ability goes to every member of the company who holds it
 * there; a platform type goes to every super admin; a type with neither is
 * personal, sent to one named person.
 */
final class NotificationType
{
    public const GROUP_SALES = 'sales';

    public const GROUP_PURCHASES = 'purchases';

    public const GROUP_RECURRING = 'recurring';

    public const GROUP_TEAM = 'team';

    public const GROUP_SYSTEM = 'system';

    /**
     * @param  string  $key  stored as the notice's type, and the translation
     *                       key under `inbox.types`
     * @param  string|null  $ability  the Bouncer ability a member needs, or
     *                                null for a personal notice
     * @param  class-string|null  $model  the model the ability is checked on
     * @param  bool  $mail  whether it is emailed by default
     * @param  bool  $platform  sent to every super admin, about the whole
     *                          installation rather than one company
     * @param  bool  $bell  whether it shows in the bell by default
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly ?string $ability = null,
        public readonly ?string $model = null,
        public readonly bool $mail = false,
        public readonly bool $platform = false,
        public readonly bool $bell = true,
    ) {}

    public function isPersonal(): bool
    {
        return $this->ability === null && ! $this->platform;
    }
}
