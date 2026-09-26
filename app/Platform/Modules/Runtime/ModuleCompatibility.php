<?php

namespace App\Platform\Modules\Runtime;

use App\Platform\Operations\Models\Setting;
use Composer\Semver\Semver;
use Throwable;

/**
 * Whether a module's `compatibility` block (from its signed manifest or its
 * module.json) fits this host: InvoiceShelf version, module runtime API, PHP
 * and extensions. Used before an install and again on every start, so a core
 * upgrade cannot leave an incompatible module enabled.
 */
final class ModuleCompatibility
{
    /**
     * Why the module does not fit this host; empty when it does.
     *
     * @return list<string>
     */
    public static function problems(mixed $compatibility): array
    {
        if (! is_array($compatibility)) {
            return ['Release compatibility metadata is invalid.'];
        }

        $problems = [];
        $appVersion = (string) config('app.version', Setting::getSetting('version'));
        $invoiceshelf = $compatibility['invoiceshelf'] ?? null;
        if (is_string($invoiceshelf) && $invoiceshelf !== '' && ! self::satisfies($appVersion, $invoiceshelf)) {
            $problems[] = 'This module requires a different InvoiceShelf version.';
        }

        $php = $compatibility['php'] ?? null;
        if (is_string($php) && $php !== '' && ! self::satisfies(PHP_VERSION, $php)) {
            $problems[] = 'This module requires a different PHP version.';
        }

        $moduleApi = $compatibility['module_api'] ?? null;
        if (! is_string($moduleApi) || ! self::satisfies((string) config('invoiceshelf.marketplace.module_api_version'), $moduleApi)) {
            $problems[] = 'This module requires an unsupported module runtime API.';
        }

        foreach (($compatibility['extensions'] ?? []) as $extension) {
            if (! is_string($extension) || ! str_starts_with($extension, 'ext-') || ! extension_loaded(substr($extension, 4))) {
                $problems[] = 'A required PHP extension is unavailable.';
                break;
            }
        }

        return $problems;
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies(ltrim($version, 'v'), $constraint);
        } catch (Throwable) {
            return false;
        }
    }
}
