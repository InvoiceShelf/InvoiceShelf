<?php

namespace App\Platform\Notifications;

use InvalidArgumentException;

/**
 * Every kind of notice the app can send, in the order the preferences page
 * lists them. The notifications provider registers the app's own; a module
 * may register more.
 */
final class NotificationCatalogue
{
    /** @var array<string, NotificationType> */
    private array $types = [];

    public function register(NotificationType $type): void
    {
        $this->types[$type->key] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): NotificationType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown notification type: {$key}");
    }

    /**
     * @return array<string, NotificationType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
