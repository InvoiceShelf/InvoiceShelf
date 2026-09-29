<?php

namespace App\Platform\Notifications;

use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Notifications\Application\Housekeeping;
use App\Platform\Notifications\Console\PruneNotifications;
use App\Platform\Notifications\Listeners\NotifyStaff;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationCatalogue::class, function (): NotificationCatalogue {
            $catalogue = new NotificationCatalogue;

            foreach (self::types() as $type) {
                $catalogue->register($type);
            }

            return $catalogue;
        });
    }

    public function boot(): void
    {
        $this->commands([PruneNotifications::class]);

        Event::subscribe(NotifyStaff::class);
        Event::listen(CompanyAccessRevoked::class, [Housekeeping::class, 'handleCompanyAccessRevoked']);
        Event::listen(UserAccessRevoked::class, [Housekeeping::class, 'handleUserAccessRevoked']);
    }

    /**
     * The app's own notices, in the order the preferences page lists them.
     *
     * @return list<NotificationType>
     */
    private static function types(): array
    {
        return [
            new NotificationType('invoice_viewed', NotificationType::GROUP_SALES, 'view-invoice', Invoice::class),
            new NotificationType('estimate_viewed', NotificationType::GROUP_SALES, 'view-estimate', Estimate::class),
            new NotificationType('recurring_invoice_generated', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('recurring_invoice_failed', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('recurring_cost_generated', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('recurring_cost_failed', NotificationType::GROUP_RECURRING, mail: true),
        ];
    }
}
