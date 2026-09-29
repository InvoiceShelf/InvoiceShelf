<?php

namespace App\Platform\Notifications\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Notifications\AppNotification;
use App\Platform\Notifications\NotificationCatalogue;
use App\Platform\Notifications\NotificationMessage;
use App\Platform\Notifications\NotificationType;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
    /**
     * How long a notice sent once is remembered, so a daily sweep does not
     * send it again.
     */
    private const ONCE_DAYS = 400;

    public function __construct(
        private readonly NotificationCatalogue $catalogue,
        private readonly NotificationPreferences $preferences,
        private readonly CompanyNotificationDefaults $companyDefaults,
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

            $channels = $this->preferences->channels($user, $type->key, $message->companyId);

            if ($channels === []) {
                continue;
            }

            $this->deliver(fn () => $user->notify(new AppNotification($message, $channels, $this->localeFor($user, $message->companyId))));
            $sent++;
        }

        return $sent;
    }

    /**
     * Send a notice about a record only the first time: a daily sweep that
     * finds the same overdue bill again tells nobody twice.
     *
     * @return int how many people it was sent to; 0 when it was sent before
     */
    public function sendOnce(NotificationMessage $message, ?User $to = null): int
    {
        $subject = $message->subject;
        $key = 'notifications:once:'.$message->type.':'.($subject ? $subject->getMorphClass().':'.$subject->getKey() : 'none').':'.($to?->id ?? 'all');

        $sentBefore = $subject !== null && DatabaseNotification::query()
            ->where('type', $message->type)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->when($to !== null, fn ($query) => $query->where('notifiable_id', $to->id))
            ->exists();

        if ($sentBefore || ! Cache::add($key, true, now()->addDays(self::ONCE_DAYS))) {
            return 0;
        }

        return $this->send($message, $to);
    }

    /**
     * Email a notice to an address outside the member list, such as a shared
     * company mailbox. Nothing is stored for the bell. A type the company
     * switched off is not sent here either.
     */
    public function mailTo(string $address, NotificationMessage $message): void
    {
        if (! ($this->companyDefaults->for($message->companyId)[$message->type]['enabled'] ?? true)) {
            return;
        }

        $locale = $this->companyLocale($message->companyId);

        $this->deliver(fn () => Notification::route('mail', $address)
            ->notify(new AppNotification($message, ['mail'], $locale)));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(NotificationType $type, NotificationMessage $message, ?User $to): Collection
    {
        if ($type->platform) {
            return User::query()->where('role', 'super admin')->get();
        }

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
