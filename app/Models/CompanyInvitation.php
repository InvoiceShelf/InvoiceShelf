<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Silber\Bouncer\Database\Role;

class CompanyInvitation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $dates = ['expires_at'];

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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
