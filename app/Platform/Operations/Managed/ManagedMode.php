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

    public static function readOnly(): bool
    {
        return self::enabled() && (bool) config('managed.read_only');
    }

    /**
     * Whether owners may install official modules here: a provider that wants
     * them mounts a writable, per-install Modules directory. Everywhere else
     * on a managed install the code is read-only and installs stay refused.
     */
    public static function modulesInstallable(): bool
    {
        $path = (string) config('modules.paths.modules');

        return $path !== '' && is_dir($path) && is_writable($path);
    }

    /**
     * What the SPA is told about it (window.managed, and the client manifest).
     *
     * @return array{support_url: string|null, modules_installable: bool, read_only: bool, billing_url: string|null}
     */
    public static function clientState(): array
    {
        return [
            'support_url' => config('managed.support_url') ?: null,
            'read_only' => self::readOnly(),
            'billing_url' => config('managed.billing_url') ?: null,
            'modules_installable' => self::modulesInstallable(),
        ];
    }
}
