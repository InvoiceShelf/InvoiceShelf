<?php

namespace App\Platform\Announcements\Application;

use App\Domains\Accounts\Application\UserLocale;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Announcements\Models\Announcement;
use App\Platform\Announcements\Models\AnnouncementDismissal;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Who sees which announcement, in which language, and writing the ones this
 * install owns.
 */
class AnnouncementService
{
    /**
     * The announcements to show this person now, in their language: live,
     * meant for them, and not dismissed since they last changed.
     *
     * @return list<array<string, mixed>>
     */
    public function activeFor(User $user, ?int $companyId): array
    {
        $admin = $this->isAdmin($user, $companyId);
        $locale = UserLocale::for($user, $companyId);

        $dismissed = AnnouncementDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('updated_at', 'announcement_id');

        return Announcement::query()
            ->live()
            ->when(! $admin, fn ($query) => $query->where('audience', 'everyone'))
            ->orderByRaw("case level when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->latest('id')
            ->get()
            ->reject(fn (Announcement $announcement): bool => isset($dismissed[$announcement->id])
                && $dismissed[$announcement->id]->greaterThanOrEqualTo($announcement->updated_at))
            ->map(fn (Announcement $announcement): array => $this->present($announcement, $locale))
            ->values()
            ->all();
    }

    public function dismiss(User $user, Announcement $announcement): void
    {
        AnnouncementDismissal::query()->updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['updated_at' => now()],
        )->touch();
    }

    /**
     * Create or change an announcement written on this install.
     *
     * @param  array<string, mixed>  $data  validated
     *
     * @throws ValidationException for an announcement from the feed
     */
    public function save(?Announcement $announcement, array $data, User $by): Announcement
    {
        if ($announcement?->isFromFeed()) {
            throw ValidationException::withMessages(['announcement' => ['announcement_from_feed']]);
        }

        $announcement ??= new Announcement(['source' => Announcement::SOURCE_LOCAL, 'created_by' => $by->id]);
        $announcement->fill([
            ...Arr::only($data, ['title', 'body', 'link_url', 'link_label', 'level', 'audience', 'starts_at', 'ends_at']),
            'translations' => $this->cleanTranslations($data['translations'] ?? []),
        ]);
        $announcement->save();

        return $announcement;
    }

    /**
     * @throws ValidationException for an announcement from the feed
     */
    public function delete(Announcement $announcement): void
    {
        if ($announcement->isFromFeed()) {
            throw ValidationException::withMessages(['announcement' => ['announcement_from_feed']]);
        }

        $announcement->dismissals()->delete();
        $announcement->delete();
    }

    /**
     * Hide or show an announcement on this install; the only change made to
     * one from the feed.
     */
    public function setHidden(Announcement $announcement, bool $hidden): Announcement
    {
        $announcement->forceFill(['hidden_at' => $hidden ? now() : null])->save();

        return $announcement;
    }

    /**
     * The text in the given language, falling back field by field to the
     * base text; `pt_BR` also finds `pt`.
     *
     * @return array<string, mixed>
     */
    public function present(Announcement $announcement, string $locale): array
    {
        $translations = (array) ($announcement->translations ?? []);
        $translation = $translations[$locale] ?? $translations[strtok($locale, '_')] ?? [];

        return [
            'id' => $announcement->id,
            'source' => $announcement->source,
            'level' => $announcement->level,
            'audience' => $announcement->audience,
            'title' => ($translation['title'] ?? '') ?: $announcement->title,
            'body' => ($translation['body'] ?? '') ?: $announcement->body,
            'link_url' => $announcement->link_url,
            'link_label' => $announcement->link_url
                ? ((($translation['link_label'] ?? '') ?: $announcement->link_label) ?: null)
                : null,
            'starts_at' => $announcement->starts_at?->toIso8601String(),
            'ends_at' => $announcement->ends_at?->toIso8601String(),
        ];
    }

    /**
     * Super admins, and the owner of the company being looked at.
     */
    private function isAdmin(User $user, ?int $companyId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $companyId !== null
            && (int) Company::query()->whereKey($companyId)->value('owner_id') === (int) $user->id;
    }

    /**
     * @param  array<string, array<string, ?string>>  $translations
     * @return array<string, array<string, string>>
     */
    private function cleanTranslations(array $translations): array
    {
        $clean = [];

        foreach ($translations as $locale => $fields) {
            $fields = array_filter(
                Arr::only((array) $fields, ['title', 'body', 'link_label']),
                fn ($value): bool => is_string($value) && trim($value) !== '',
            );

            if ($fields !== []) {
                $clean[$locale] = $fields;
            }
        }

        ksort($clean);

        return $clean;
    }
}
