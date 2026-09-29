<?php

namespace App\Platform\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * What one notice says, before it is addressed to anyone.
 *
 * The words are not stored: the title and body are translation keys under
 * `inbox.types.{type}`, filled in with the params, so the bell reads in the
 * viewer's language and the email in the recipient's.
 */
final class NotificationMessage
{
    /**
     * @param  string  $type  a key in the catalogue
     * @param  int|null  $companyId  the company it belongs to; null for a
     *                               platform notice
     * @param  Model|null  $subject  the record it is about
     * @param  array<string, string>  $params  the values the text is filled in with
     * @param  list<string>  $translate  params whose value is itself a
     *                                   translation key, such as an error code
     * @param  string|null  $variant  picks `body_{variant}` over `body`
     * @param  string|null  $url  where opening the notice leads, an app path
     *                            such as `/admin/invoices/5/view`
     */
    public function __construct(
        public readonly string $type,
        public readonly ?int $companyId,
        public readonly ?Model $subject = null,
        public readonly array $params = [],
        public readonly array $translate = [],
        public readonly ?string $variant = null,
        public readonly ?string $url = null,
    ) {}

    public function titleKey(): string
    {
        return "inbox.types.{$this->type}.title";
    }

    public function bodyKey(): string
    {
        return "inbox.types.{$this->type}.".($this->variant ? "body_{$this->variant}" : 'body');
    }

    /**
     * The stored form, which the SPA renders.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->titleKey(),
            'body' => $this->bodyKey(),
            'params' => (object) $this->params,
            'translate' => $this->translate,
            'url' => $this->url,
        ];
    }
}
