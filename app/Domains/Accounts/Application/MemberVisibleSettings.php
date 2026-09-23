<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Mail\Contracts\MailConfigurator;
use Illuminate\Support\Collection;

/**
 * A company's preferences as any of its members may read them.
 *
 * Two kinds of setting are left out, because they can hold credentials and
 * have endpoints of their own that only the owner reaches: the company's mail
 * transport and the settings of its modules (`module.{slug}.{key}`).
 */
class MemberVisibleSettings
{
    private const MODULE_PREFIX = 'module.';

    public function __construct(private readonly MailConfigurator $mailConfigurator) {}

    /**
     * Every preference on file that a member may read.
     */
    public function all(int|string $companyId): Collection
    {
        return CompanySetting::getAllSettings($companyId)
            ->reject(fn (mixed $value, string $key): bool => $this->isPrivate($key));
    }

    /**
     * The named preferences a member may read; the rest are left out, as a
     * preference with no row on file is.
     *
     * @param  array<int, mixed>  $keys
     */
    public function only(array $keys, int|string $companyId): Collection
    {
        $readable = array_filter($keys, fn (mixed $key): bool => is_string($key) && ! $this->isPrivate($key));

        return CompanySetting::getSettings(array_values($readable), $companyId);
    }

    private function isPrivate(string $key): bool
    {
        return str_starts_with($key, self::MODULE_PREFIX)
            || in_array($key, $this->mailConfigurator->getCompanySettingKeys(), true);
    }
}
