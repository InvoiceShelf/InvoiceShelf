<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer accepted or rejected an estimate in the portal. The status is
 * the estimate's new one, ACCEPTED or REJECTED.
 */
class EstimateAnswered
{
    use Dispatchable;

    public function __construct(
        public readonly int $estimateId,
        public readonly int $companyId,
        public readonly string $status,
    ) {}
}
