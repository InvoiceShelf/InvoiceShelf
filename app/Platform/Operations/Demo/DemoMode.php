<?php

namespace App\Platform\Operations\Demo;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Carbon\CarbonImmutable;
use Cron\CronExpression;

/**
 * The public demo: an install anyone can sign in to, rebuilt from scratch on
 * a schedule. It is on when APP_ENV is `demo`.
 */
final class DemoMode
{
    public static function enabled(): bool
    {
        return config('app.env') === 'demo';
    }

    /**
     * When the demo is next rebuilt, from the reset schedule.
     */
    public static function nextResetAt(): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::instance(
                (new CronExpression((string) config('invoiceshelf.demo.reset_cron')))->getNextRunDate(now()),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The marketplace releases the demo installs, from DEMO_MODULES
     * ("tasks-projects@0.2.0,other@1.0.0").
     *
     * @return list<array{slug: string, version: string}>
     */
    public static function modules(): array
    {
        $modules = [];

        foreach (explode(',', (string) config('invoiceshelf.demo.modules')) as $entry) {
            [$slug, $version] = array_pad(explode('@', trim($entry), 2), 2, '');

            if ($slug !== '' && $version !== '') {
                $modules[] = ['slug' => $slug, 'version' => $version];
            }
        }

        return $modules;
    }

    /**
     * The sign-ins a visitor is offered.
     *
     * @return array{email: string, password: string, portal_email: string, portal_password: string}
     */
    public static function credentials(): array
    {
        return [
            'email' => (string) config('invoiceshelf.demo.email'),
            'password' => (string) config('invoiceshelf.demo.password'),
            'portal_email' => (string) config('invoiceshelf.demo.portal_email'),
            'portal_password' => (string) config('invoiceshelf.demo.portal_password'),
        ];
    }

    /**
     * What the SPA shows visitors: the sign-ins to offer, where the customer
     * portal signs in, and when the next reset wipes their changes.
     *
     * @return array{next_reset_at: string|null, email: string, password: string, portal_email: string, portal_password: string, portal_path: string|null}
     */
    public static function clientState(): array
    {
        $credentials = self::credentials();

        $slug = self::company()?->slug;

        return [
            'next_reset_at' => self::nextResetAt()?->toIso8601String(),
            ...$credentials,
            'portal_path' => $slug ? "/{$slug}/customer/login" : null,
        ];
    }

    /**
     * The company the demo owner signs in to, once the demo has been built.
     */
    public static function company(): ?Company
    {
        return User::query()->where('email', self::credentials()['email'])->first()?->companies()->orderBy('companies.id')->first();
    }
}
