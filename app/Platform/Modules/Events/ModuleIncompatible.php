<?php

namespace App\Platform\Modules\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An enabled module was switched off after an upgrade because it does not
 * fit this InvoiceShelf version.
 */
class ModuleIncompatible
{
    use Dispatchable;

    /**
     * @param  string  $problems  why, in words, as the modules page shows it
     */
    public function __construct(
        public readonly string $module,
        public readonly string $problems,
    ) {}
}
