<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One person's notices in the bell: those of the company they are looking
 * at, and platform notices, which belong to no company.
 */
class Inbox
{
    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function list(User $user, ?int $companyId, bool $unreadOnly, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($user, $companyId)
            ->when($unreadOnly, fn (Builder $query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage);
    }

    public function unreadCount(User $user, ?int $companyId): int
    {
        return $this->query($user, $companyId)->whereNull('read_at')->count();
    }

    /**
     * The person's notice, or none when it is someone else's or another
     * company's.
     */
    public function find(User $user, ?int $companyId, string $id): ?DatabaseNotification
    {
        return $this->query($user, $companyId)->whereKey($id)->first();
    }

    public function markAllRead(User $user, ?int $companyId): int
    {
        return $this->query($user, $companyId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function query(User $user, ?int $companyId): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where(fn (Builder $query) => $query->whereNull('company_id')
                ->when($companyId !== null, fn (Builder $scoped) => $scoped->orWhere('company_id', $companyId)));
    }
}
