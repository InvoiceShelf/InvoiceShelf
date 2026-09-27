<?php

namespace App\Domains\Sales\Models;

use App\Domains\Catalog\Models\Item;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Taxation\Models\Tax;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A line snapshot shared by estimates and quotes.
 *
 * The row is a snapshot taken when the offer was raised: the name, the
 * description, the unit price and the computed total are stored here rather
 * than read back from the catalog, so editing an item later never rewrites an
 * offer that has already gone out. The link to the catalog entry is kept for
 * reporting only and may be absent on a free-text line. Amounts are integer
 * minor units; quantity and the discount percentage are the fractional ones.
 */
abstract class SalesProposalItem extends Model
{
    use HasCustomFields;
    use HasFactory;

    abstract protected function documentKind(): string;

    abstract protected function documentClass(): string;

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'total' => 'integer',
            'discount' => 'float',
            'quantity' => 'float',
            'discount_val' => 'integer',
            'tax' => 'integer',
        ];
    }

    /**
     * Offer the line belongs to.
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo($this->documentClass(), $this->documentKind().'_id', 'id');
    }

    /**
     * Catalog entry the line was built from, when there was one.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    /**
     * Taxes charged on this line, used when the document is in per-item tax
     * mode.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, $this->documentKind().'_item_id', 'id');
    }

    /**
     * Narrow to one company. The column is left unqualified, as the callers
     * pass a plain estimate-item query.
     */
    public function scopeWhereCompany(Builder $query, int $company_id): void
    {
        $query->where('company_id', '=', $company_id);
    }
}
