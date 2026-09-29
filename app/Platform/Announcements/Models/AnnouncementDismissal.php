<?php

namespace App\Platform\Announcements\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person put an announcement away. It stays away until the
 * announcement is edited after the dismissal.
 */
class AnnouncementDismissal extends Model
{
    protected $table = 'announcement_dismissals';

    protected $guarded = ['id'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
