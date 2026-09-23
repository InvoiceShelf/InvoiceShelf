<?php

namespace App\Domains\Accounts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user lost access to a company: removed from it, or the company deleted.
 *
 * Anything that granted access scoped to that company on the user's behalf
 * should end it.
 */
class CompanyAccessRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $companyId,
    ) {}
}
