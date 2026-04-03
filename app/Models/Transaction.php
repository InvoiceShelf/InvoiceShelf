<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $dates = [
        'transaction_date',
    ];

    public const FAILED = 'FAILED';

    public const SUCCESS = 'SUCCESS';

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if a completed transaction's public link has expired based on the
     * company's link expiry settings (link_expiry_days and automatically_expire_public_links).
     */
    public function isExpired(): bool
    {
        $linkExpiryDays = (int) CompanySetting::getSetting('link_expiry_days', $this->company_id);
        $checkExpiryLinks = CompanySetting::getSetting('automatically_expire_public_links', $this->company_id);

        $expiryDate = $this->updated_at->addDays($linkExpiryDays);

        if ($checkExpiryLinks == 'YES' && $this->status == self::SUCCESS && Carbon::now()->format('Y-m-d') > $expiryDate->format('Y-m-d')) {
            return true;
        }

        return false;
    }
}
