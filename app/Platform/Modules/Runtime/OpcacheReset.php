<?php

namespace App\Platform\Modules\Runtime;

/**
 * Clears PHP's opcode cache after module files change. Images that never
 * re-read files (opcache.validate_timestamps=0, such as the cloud runtime)
 * would otherwise keep serving an updated or removed module's old code.
 * Installs run in a web request, so this clears the FPM pool's cache; from
 * the command line it cannot reach FPM, and the container must restart.
 */
final class OpcacheReset
{
    public static function afterCodeChange(): bool
    {
        if (PHP_SAPI === 'cli' || ! function_exists('opcache_reset')) {
            return false;
        }

        return opcache_reset();
    }
}
