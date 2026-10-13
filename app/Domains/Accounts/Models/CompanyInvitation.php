<?php

namespace App\Domains\Accounts\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Silber\Bouncer\Database\Role;

class CompanyInvitation extends Model
{
    protected $table = 'company_invitations';

    use HasFactory;

    protected $guarded = ['id'];

    protected $dates = ['expires_at'];

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
        ];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * The role the invitation grants. It is one specific role of the inviting
     * company, so Bouncer's filter to whichever company is current does not
     * apply: a public invitation page has no current company at all.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class)->withoutGlobalScopes();
    }

    /**
     * Roles offered by this invitation, constrained to its company.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        $ids = collect($this->role_ids ?: [$this->role_id])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return Role::query()
            ->withoutGlobalScopes()
            ->where('scope', $this->company_id)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Titles of all roles offered by this invitation, including legacy rows.
     */
    public function roleNames(): string
    {
        return $this->roles()->pluck('title')->implode(', ');
    }

    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }

    /**
     * Scope to pending, non-expired invitations.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope to invitations for a specific user (by user_id or email).
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('email', $user->email);
        });
    }
}
