<?php

namespace App\Domains\Accounts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user's OAuth grants were all revoked, because the account is going away.
 */
class UserAccessRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
    ) {}
}
