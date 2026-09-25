<?php

namespace App\Domains\Accounts\Application\Exceptions;

use RuntimeException;

/**
 * A preset cannot be removed while a member holds it or an invitation offers it.
 */
class RolePresetInUse extends RuntimeException
{
    public function __construct(public readonly int $members, public readonly int $invitations)
    {
        parent::__construct("The preset is still held by {$members} member(s) and offered by {$invitations} pending invitation(s).");
    }
}
