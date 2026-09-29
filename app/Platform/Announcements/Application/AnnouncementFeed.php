<?php

namespace App\Platform\Announcements\Application;

use App\Platform\Announcements\Models\Announcement;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies the InvoiceShelf project's announcements from the website this
 * install already asks about updates (`invoiceshelf.base_url`).
 *
 * The feed is the whole truth for feed announcements: what it lists is
 * stored, what it stops listing is removed. A super admin's choice to hide
 * one here is kept. When the feed cannot be read, nothing changes.
 */
class AnnouncementFeed
{
    public const TIMEOUT_SECONDS = 5;

    /**
     * @return array{synced: int, removed: int}|null null when switched off or unreachable
     */
    public function sync(): ?array
    {
        if (! config('invoiceshelf.announcements.feed')) {
            return null;
        }

        $items = $this->fetch();

        if ($items === null) {
            return null;
        }

        return DB::transaction(function () use ($items): array {
            $seen = [];

            foreach ($items as $item) {
                $id = (string) $item['id'];
                $seen[] = $id;

                Announcement::query()->updateOrCreate(
                    ['external_id' => $id, 'source' => Announcement::SOURCE_FEED],
                    [
                        'title' => $item['title'],
                        'body' => $item['body'],
                        'link_url' => $item['link_url'],
                        'link_label' => $item['link_label'],
                        'level' => $item['level'],
                        'audience' => $item['audience'],
                        'starts_at' => $item['starts_at'],
                        'ends_at' => $item['ends_at'],
                        'translations' => $item['translations'],
                    ],
                );
            }

            $gone = Announcement::query()
                ->where('source', Announcement::SOURCE_FEED)
                ->whereNotIn('external_id', $seen)
                ->get();

            foreach ($gone as $announcement) {
                $announcement->dismissals()->delete();
                $announcement->delete();
            }

            return ['synced' => count($seen), 'removed' => $gone->count()];
        });
    }

    /**
     * The feed's announcements that are well formed, or null when the feed
     * cannot be read.
     *
     * @return list<array<string, mixed>>|null
     */
    private function fetch(): ?array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('invoiceshelf.base_url'), '/'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get('api/announcements', [
                    'version' => Setting::getSetting('version'),
                    'channel' => Setting::getSetting('updater_channel') === 'insider' ? 'insider' : 'stable',
                ]);
        } catch (Throwable $error) {
            Log::warning('Announcements feed could not be reached.', ['error' => $error->getMessage()]);

            return null;
        }

        $announcements = $response->json('announcements');

        if (! $response->successful() || $response->json('success') !== true || ! is_array($announcements)) {
            Log::warning('Announcements feed answered unexpectedly.', ['status' => $response->status()]);

            return null;
        }

        return array_values(array_filter(array_map($this->normalise(...), $announcements)));
    }

    /**
     * One feed item in the shape stored here, or null when it is not usable.
     *
     * @return array<string, mixed>|null
     */
    private function normalise(mixed $item): ?array
    {
        if (! is_array($item) || ! is_string($item['id'] ?? null) || $item['id'] === ''
            || ! is_string($item['title'] ?? null) || ! is_string($item['body'] ?? null)) {
            return null;
        }

        $link = is_string($item['link_url'] ?? null) && str_starts_with($item['link_url'], 'https://')
            ? mb_substr($item['link_url'], 0, 500)
            : null;

        return [
            'id' => mb_substr($item['id'], 0, 64),
            'title' => mb_substr($item['title'], 0, 255),
            'body' => mb_substr($item['body'], 0, 5000),
            'link_url' => $link,
            'link_label' => $link && is_string($item['link_label'] ?? null) ? mb_substr($item['link_label'], 0, 255) : null,
            'level' => in_array($item['level'] ?? null, Announcement::LEVELS, true) ? $item['level'] : 'info',
            'audience' => in_array($item['audience'] ?? null, Announcement::AUDIENCES, true) ? $item['audience'] : 'everyone',
            'starts_at' => $this->date($item['starts_at'] ?? null),
            'ends_at' => $this->date($item['ends_at'] ?? null),
            'translations' => $this->translations($item['translations'] ?? []),
        ];
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return now()->parse($value)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function translations(mixed $translations): array
    {
        $clean = [];

        foreach (is_array($translations) ? $translations : [] as $locale => $fields) {
            if (! is_string($locale) || preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale) !== 1 || ! is_array($fields)) {
                continue;
            }

            $fields = array_filter(
                Arr::only($fields, ['title', 'body', 'link_label']),
                fn ($value): bool => is_string($value) && $value !== '',
            );

            if ($fields !== []) {
                $clean[$locale] = array_map(fn (string $value): string => mb_substr($value, 0, 5000), $fields);
            }
        }

        return $clean;
    }
}
