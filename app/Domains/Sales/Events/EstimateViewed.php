<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer opened an estimate for the first time, from its emailed link.
 */
class EstimateViewed
{
    use Dispatchable;

    public function __construct(
        public readonly int $estimateId,
        public readonly int $companyId,
    ) {}
}
