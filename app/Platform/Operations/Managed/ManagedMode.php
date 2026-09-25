<?php

namespace App\Platform\Operations\Managed;

/**
 * An install a hosting provider runs for its owner. On when
 * INVOICESHELF_MANAGED is true.
 */
final class ManagedMode
{
    public static function enabled(): bool
    {
        return (bool) config('managed.enabled');
    }

    /**
     * What the SPA is told about it (window.managed, and the client manifest).
     *
     * @return array{support_url: string|null}
     */
    public static function clientState(): array
    {
        return [
            'support_url' => config('managed.support_url') ?: null,
        ];
    }
}
