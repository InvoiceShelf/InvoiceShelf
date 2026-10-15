<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Notifications\AppNotification;
use App\Platform\Notifications\NotificationCatalogue;
use App\Platform\Notifications\NotificationMessage;
use App\Platform\Notifications\NotificationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Silber\Bouncer\BouncerFacade;
use Throwable;

/**
 * Sends a notice to the staff it concerns, each on the channels they chose.
 *
 * A notice is a courtesy: a mail server that is down, or anything else that
 * goes wrong while telling someone, is reported and never allowed to fail
 * the action the notice is about.
 */
class NotificationCenter
{
    public function __construct(
        private readonly NotificationCatalogue $catalogue,
        private readonly NotificationPreferences $preferences,
    ) {}

    /**
     * Send a company-wide notice to every member allowed to see its kind of
     * record, or a personal one to the given person while they are still a
     * member of the company.
     *
     * @param  list<int>  $except  people not to tell, such as whoever did it
     * @return int how many people it was sent to
     */
    public function send(NotificationMessage $message, ?User $to = null, array $except = []): int
    {
        $type = $this->catalogue->get($message->type);
        $sent = 0;

        foreach ($this->recipients($type, $message, $to) as $user) {
            if (in_array((int) $user->id, $except, true)) {
                continue;
            }

            $channels = $this->preferences->channels($user, $type->key);

            if ($channels === []) {
                continue;
            }

            $this->deliver(fn () => $user->notify(new AppNotification($message, $channels, $this->localeFor($user, $message->companyId))));
            $sent++;
        }

        return $sent;
    }

    /**
     * Email a notice to an address outside the member list, such as a shared
     * company mailbox. Nothing is stored for the bell.
     */
    public function mailTo(string $address, NotificationMessage $message): void
    {
        $locale = $this->companyLocale($message->companyId);

        $this->deliver(fn () => Notification::route('mail', $address)
            ->notify(new AppNotification($message, ['mail'], $locale)));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(NotificationType $type, NotificationMessage $message, ?User $to): Collection
    {
        if ($type->isPersonal()) {
            $member = $to !== null && ($message->companyId === null || $to->hasCompany($message->companyId));

            return collect($member ? [$to] : []);
        }

        if ($message->companyId === null) {
            return collect();
        }

        $companyId = $message->companyId;

        return User::query()
            ->whereHas('companies', fn ($query) => $query->whereKey($companyId))
            ->get()
            ->filter(fn (User $user): bool => BouncerFacade::scope()->onceTo(
                $companyId,
                fn (): bool => $type->model ? $user->can($type->ability, $type->model) : $user->can($type->ability),
            ))
            ->values();
    }

    private function deliver(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $error) {
            report($error);
        }
    }

    /**
     * The person's own language, or their company's when they follow it.
     */
    private function localeFor(User $user, ?int $companyId): string
    {
        $language = $user->getSettings(['language'])->get('language');

        return $language && $language !== 'default' ? (string) $language : $this->companyLocale($companyId);
    }

    private function companyLocale(?int $companyId): string
    {
        $language = $companyId ? CompanySetting::getSetting('language', $companyId) : null;

        return $language ? (string) $language : (string) config('app.locale', 'en');
    }
}
