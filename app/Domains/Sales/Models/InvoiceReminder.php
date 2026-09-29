<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment reminder for an invoice: sent, skipped (the customer has no
 * address) or failed. A scheduled one names its offset from the due date and
 * exists once per invoice; one sent by hand has none.
 */
class InvoiceReminder extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $table = 'invoice_reminders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
