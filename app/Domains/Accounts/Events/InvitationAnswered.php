<?php

namespace App\Domains\Accounts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone accepted or declined an invitation to a company. The status is
 * the invitation's new one.
 */
class InvitationAnswered
{
    use Dispatchable;

    public function __construct(
        public readonly int $invitationId,
        public readonly int $companyId,
        public readonly string $status,
    ) {}
}
