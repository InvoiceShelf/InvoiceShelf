<?php

namespace App\Platform\Notifications;

use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Receivables\Models\Payment;
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
            new NotificationType('estimate_accepted', NotificationType::GROUP_SALES, 'view-estimate', Estimate::class, mail: true),
            new NotificationType('estimate_rejected', NotificationType::GROUP_SALES, 'view-estimate', Estimate::class, mail: true),
            new NotificationType('payment_received', NotificationType::GROUP_SALES, 'view-payment', Payment::class),
            new NotificationType('invoice_paid', NotificationType::GROUP_SALES, 'view-invoice', Invoice::class),
            new NotificationType('invoice_overdue', NotificationType::GROUP_SALES, 'view-invoice', Invoice::class),
            new NotificationType('invoice_reminder_sent', NotificationType::GROUP_SALES, 'view-invoice', Invoice::class, bell: false),
            new NotificationType('bill_due_soon', NotificationType::GROUP_PURCHASES, 'view-bill', Bill::class),
            new NotificationType('bill_overdue', NotificationType::GROUP_PURCHASES, 'view-bill', Bill::class),
            new NotificationType('recurring_invoice_generated', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('recurring_invoice_failed', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('recurring_cost_generated', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('recurring_cost_failed', NotificationType::GROUP_RECURRING, mail: true),
            new NotificationType('invitation_accepted', NotificationType::GROUP_TEAM),
            new NotificationType('invitation_declined', NotificationType::GROUP_TEAM),
            new NotificationType('ai_connection_added', NotificationType::GROUP_TEAM, mail: true),
            new NotificationType('module_disabled', NotificationType::GROUP_SYSTEM, mail: true, platform: true),
            new NotificationType('backup_failed', NotificationType::GROUP_SYSTEM, platform: true),
        ];
    }
}
