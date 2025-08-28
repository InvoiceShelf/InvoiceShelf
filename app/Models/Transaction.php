<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vinkla\Hashids\Facades\Hashids;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $dates = [
        'transaction_date',
    ];

    public const PENDING = 'PENDING';

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

    public function completeTransaction()
    {
        $this->status = self::SUCCESS;
        $this->save();
    }

    public function failedTransaction()
    {
        $this->status = self::FAILED;
        $this->save();
    }

    public static function createTransaction($data)
    {
        $transaction = self::create($data);
        $transaction->unique_hash = Hashids::connection(Transaction::class)->encode($transaction->id);
        $transaction->save();

        return $transaction;
    }

    public function isExpired()
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
