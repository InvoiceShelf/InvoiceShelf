<?php

namespace App\Platform\Announcements\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A notice for everyone on the install: a banner at the top of the app for
 * warning and critical ones, and pinned in the notification bell.
 *
 * `source` says where it came from: written here, or copied from the
 * InvoiceShelf project feed (`external_id` is then the feed's id, and only
 * `hidden_at` is ever changed locally). `translations` holds the text per
 * locale as `{locale: {title, body, link_label}}`.
 */
class Announcement extends Model
{
    public const SOURCE_LOCAL = 'local';

    public const SOURCE_FEED = 'feed';

    public const LEVELS = ['info', 'warning', 'critical'];

    /** `admins` means super admins and the owners of the company being looked at. */
    public const AUDIENCES = ['everyone', 'admins'];

    protected $table = 'announcements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hidden_at' => 'datetime',
            'translations' => 'array',
        ];
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    /**
     * Inside its window now, and not hidden on this install.
     */
    public function scopeLive(Builder $query): void
    {
        $now = now();

        $query->whereNull('hidden_at')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    public function isFromFeed(): bool
    {
        return $this->source === self::SOURCE_FEED;
    }
}
