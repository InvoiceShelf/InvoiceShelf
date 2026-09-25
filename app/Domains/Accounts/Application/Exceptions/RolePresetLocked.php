<?php

namespace App\Domains\Accounts\Application\Exceptions;

use RuntimeException;

/**
 * The Owner preset always holds every ability and cannot be changed or removed.
 */
class RolePresetLocked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Owner preset cannot be changed or removed.');
    }
}
