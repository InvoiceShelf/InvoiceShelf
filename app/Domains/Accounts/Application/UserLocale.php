<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;

/**
 * The language the SPA would speak to a user: their own, or their company's
 * when they left it at the default, or English.
 */
final class UserLocale
{
    public static function for(User $user, ?int $companyId): string
    {
        $language = $user->getSettings(['language'])->get('language');

        if ((! $language || $language === 'default') && $companyId !== null) {
            $language = CompanySetting::getSetting('language', $companyId);
        }

        return is_string($language) && $language !== '' && $language !== 'default' ? $language : 'en';
    }
}
