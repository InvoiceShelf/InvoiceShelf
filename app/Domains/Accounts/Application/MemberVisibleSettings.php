<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Mail\Contracts\MailConfigurator;
use Illuminate\Support\Collection;

/**
 * A company's preferences as any of its members may read them, and as the
 * generic settings endpoint may write them.
 *
 * Two kinds of setting are left out, because they can hold credentials and
 * have endpoints of their own that only the owner reaches, with their own
 * validation: the company's mail transport and the settings of its modules
 * (`module.{slug}.{key}`).
 *
 * Some preferences members may read are kept by the application itself, and
 * the generic endpoint never writes them: `bulk_exchange_rate_configured`
 * records that the one-time exchange-rate backfill has run, and setting it back
 * would let it run again.
 */
class MemberVisibleSettings
{
    private const MODULE_PREFIX = 'module.';

    private const KEPT_BY_THE_APPLICATION = [
        'bulk_exchange_rate_configured',
    ];

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

    /**
     * Whether the application keeps this preference itself, so that no request
     * may write it.
     */
    public function isKeptByTheApplication(string $key): bool
    {
        return in_array($key, self::KEPT_BY_THE_APPLICATION, true);
    }

    /**
     * Whether the key belongs to one of those endpoints of its own.
     */
    public function isPrivate(string $key): bool
    {
        return str_starts_with($key, self::MODULE_PREFIX)
            || in_array($key, $this->mailConfigurator->getCompanySettingKeys(), true);
    }
}
