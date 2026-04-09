<?php

namespace App\Support;

use Illuminate\Http\Request;

class InstallWizardAuth
{
    public const HEADER = 'X-Install-Wizard';

    public const HEADER_VALUE = '1';

    public const TOKEN_NAME = 'installation-wizard';

    public const TOKEN_ABILITY = 'installation:wizard';

    public static function isRequest(Request $request): bool
    {
        return $request->headers->get(self::HEADER) === self::HEADER_VALUE;
    }
}
