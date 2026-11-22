<?php

namespace App\Platform\Announcements;

use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Platform\Announcements\Console\SyncAnnouncements;
use App\Platform\Announcements\Models\AnnouncementDismissal;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AnnouncementsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([SyncAnnouncements::class]);

        // A deleted account's dismissals go with it.
        Event::listen(UserAccessRevoked::class, fn (UserAccessRevoked $event) => AnnouncementDismissal::query()
            ->where('user_id', $event->userId)
            ->delete());
    }
}
