<?php

namespace App\Modules\Activators;

use Nwidart\Modules\Activators\FileActivator;

class SafeModeFileActivator extends FileActivator
{
    public function isActive($module): bool
    {
        if ((bool) config('modules.safe_mode', false)) {
            return false;
        }

        return parent::isActive($module);
    }
}
