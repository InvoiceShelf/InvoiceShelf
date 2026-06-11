<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function mailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the email log's public link has expired based on the owning
     * company's link expiry settings (link_expiry_days and automatically_expire_public_links).
     */
    public function isExpired(): bool
    {
        $mailable = $this->mailable;

        // A token whose target document no longer resolves is treated as
        // expired/invalid rather than throwing.
        if (! $mailable) {
            return true;
        }

        $linkExpiryDays = (int) CompanySetting::getSetting('link_expiry_days', $mailable->company_id);
        $checkExpiryLinks = CompanySetting::getSetting('automatically_expire_public_links', $mailable->company_id);

        $expiryDate = $this->created_at->addDays($linkExpiryDays);

        if ($checkExpiryLinks == 'YES' && Carbon::now()->format('Y-m-d') > $expiryDate->format('Y-m-d')) {
            return true;
        }

        return false;
    }
}
