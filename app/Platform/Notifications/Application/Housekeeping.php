<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Domains\Accounts\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Keeps the notifications table from growing without end, and from holding
 * notices for people who can no longer read them.
 */
class Housekeeping
{
    /**
     * Read notices are kept this many days, unread ones longer.
     */
    public const KEEP_READ_DAYS = 90;

    public const KEEP_UNREAD_DAYS = 180;

    /**
     * @return int how many notices were deleted
     */
    public function prune(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $read = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', $now->copy()->subDays(self::KEEP_READ_DAYS))
            ->delete();

        $unread = DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', $now->copy()->subDays(self::KEEP_UNREAD_DAYS))
            ->delete();

        return $read + $unread;
    }

    /**
     * Someone left a company, or it was deleted: their notices about it go.
     */
    public function handleCompanyAccessRevoked(CompanyAccessRevoked $event): void
    {
        $this->forUser($event->userId)->where('company_id', $event->companyId)->delete();
    }

    /**
     * The account was deleted: all its notices go.
     */
    public function handleUserAccessRevoked(UserAccessRevoked $event): void
    {
        $this->forUser($event->userId)->delete();
    }

    private function forUser(int $userId)
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $userId);
    }
}
