<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use InvoiceShelf\Modules\Registry;

/**
 * The configured catalogue, followed by whatever the enabled modules have
 * registered with the module SDK.
 *
 * Nothing is memoized: a module's provider boots part-way through a request
 * when it is enabled, so the catalogue has to be read again every time rather
 * than frozen at the first call.
 */
class ModuleAwareAbilityCatalog implements AbilityCatalog
{
    /**
     * Defaults filled in for the optional keys, so every consumer can read the
     * whole shape unguarded. Module entries arrive already normalized by the
     * SDK registry; it is the host configuration that leaves keys out.
     */
    private const DEFAULTS = [
        'model' => null,
        'depends_on' => [],
        'owner_only' => false,
    ];

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        $entries = array_merge(
            config('abilities.abilities', []),
            Registry::allAbilities(),
        );

        return array_map(
            static fn (array $entry): array => $entry + self::DEFAULTS,
            array_values($entries),
        );
    }
}
