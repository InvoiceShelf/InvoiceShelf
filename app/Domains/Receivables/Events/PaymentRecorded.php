<?php

namespace App\Domains\Receivables\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer payment was recorded, by a member (actorId), or by an online
 * payment or the system (no actor).
 */
class PaymentRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly int $paymentId,
        public readonly int $companyId,
        public readonly ?int $actorId,
    ) {}
}
