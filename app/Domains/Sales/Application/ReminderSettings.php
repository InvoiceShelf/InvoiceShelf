<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\CompanySetting;

/**
 * A company's payment reminder settings, with the defaults for anything the
 * owner has not set. Reminders are off until the owner turns them on.
 */
final class ReminderSettings
{
    public const DEFAULT_OFFSETS = [-3, 1, 7, 14];

    public const DEFAULT_SEND_HOUR = 9;

    public const DEFAULT_SUBJECT = 'Reminder: invoice {INVOICE_NUMBER}';

    public const DEFAULT_BODY = 'Hello {CONTACT_DISPLAY_NAME},<br><br>This is a friendly reminder that invoice <b>{INVOICE_NUMBER}</b> from <b>{COMPANY_NAME}</b> was due on {INVOICE_DUE_DATE}. <b>{INVOICE_DUE_AMOUNT}</b> is still open.<br><br>If you have already paid, please ignore this message. Thank you!';

    /** The furthest before or after the due date a reminder may be scheduled. */
    public const MAX_OFFSET = 365;

    public const MAX_OFFSETS = 10;

    private const KEYS = [
        'reminders_enabled',
        'reminders_offsets',
        'reminders_send_hour',
        'reminders_subject',
        'reminders_body',
        'reminders_attach_pdf',
    ];

    /**
     * @param  list<int>  $offsets  sorted, distinct days from the due date
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $offsets,
        public readonly int $sendHour,
        public readonly string $subject,
        public readonly string $body,
        public readonly bool $attachPdf,
    ) {}

    public static function for(int $companyId): self
    {
        $stored = CompanySetting::getSettings(self::KEYS, $companyId);
        $offsets = json_decode((string) ($stored['reminders_offsets'] ?? ''), true);
        $hour = $stored['reminders_send_hour'] ?? null;

        return new self(
            enabled: ($stored['reminders_enabled'] ?? 'NO') === 'YES',
            offsets: is_array($offsets) ? self::normalise($offsets) : self::DEFAULT_OFFSETS,
            sendHour: is_numeric($hour) ? max(0, min(23, (int) $hour)) : self::DEFAULT_SEND_HOUR,
            subject: (string) (($stored['reminders_subject'] ?? '') ?: self::DEFAULT_SUBJECT),
            body: (string) (($stored['reminders_body'] ?? '') ?: self::DEFAULT_BODY),
            attachPdf: ($stored['reminders_attach_pdf'] ?? 'NO') === 'YES',
        );
    }

    /**
     * Store the given values; anything left out keeps its current value.
     *
     * @param  array{enabled?: bool, offsets?: list<int>, send_hour?: int, subject?: string, body?: string, attach_pdf?: bool}  $values
     */
    public static function save(int $companyId, array $values): void
    {
        $settings = [];

        if (array_key_exists('enabled', $values)) {
            $settings['reminders_enabled'] = $values['enabled'] ? 'YES' : 'NO';
        }
        if (array_key_exists('offsets', $values)) {
            $settings['reminders_offsets'] = json_encode(self::normalise($values['offsets']));
        }
        if (array_key_exists('send_hour', $values)) {
            $settings['reminders_send_hour'] = (int) $values['send_hour'];
        }
        if (array_key_exists('subject', $values)) {
            $settings['reminders_subject'] = (string) $values['subject'];
        }
        if (array_key_exists('body', $values)) {
            $settings['reminders_body'] = (string) $values['body'];
        }
        if (array_key_exists('attach_pdf', $values)) {
            $settings['reminders_attach_pdf'] = $values['attach_pdf'] ? 'YES' : 'NO';
        }

        if ($settings !== []) {
            CompanySetting::setSettings($settings, $companyId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'offsets' => $this->offsets,
            'send_hour' => $this->sendHour,
            'subject' => $this->subject,
            'body' => $this->body,
            'attach_pdf' => $this->attachPdf,
        ];
    }

    /**
     * @param  array<int, mixed>  $offsets
     * @return list<int>
     */
    private static function normalise(array $offsets): array
    {
        $days = array_values(array_unique(array_map('intval', array_filter($offsets, 'is_numeric'))));
        $days = array_values(array_filter($days, fn (int $day): bool => abs($day) <= self::MAX_OFFSET));
        sort($days);

        return array_slice($days, 0, self::MAX_OFFSETS);
    }
}
