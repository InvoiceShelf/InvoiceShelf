<?php

namespace App\Domains\Accounts\Contracts;

/**
 * The full catalogue of abilities a role can be granted.
 *
 * The host's own abilities are configuration, but a module may contribute more
 * while it is enabled, so the catalogue is a port rather than a config read:
 * the answer depends on what is running, not only on what is on disk.
 */
interface AbilityCatalog
{
    /**
     * Every ability on offer, in catalogue order.
     *
     * Each entry carries `name`, `ability`, `model`, `depends_on` and
     * `owner_only`; callers never have to test for a missing key.
     *
     * @return list<array{name: string, ability: string, model: class-string|null, depends_on: list<string>, owner_only: bool}>
     */
    public function all(): array;
}
