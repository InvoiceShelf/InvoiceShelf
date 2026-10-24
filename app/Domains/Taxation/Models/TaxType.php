<?php

namespace App\Domains\Taxation\Models;

use App\Domains\Accounts\Models\Company;
use App\Support\SafeOrderBy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company's definition of a tax: how it is worked out (a percentage, or a
 * fixed amount in minor units), which side of the ledger it belongs to, and
 * whether it stacks on top of other taxes.
 *
 * `type` separates the two kinds of rows in this table. GENERAL rows are the
 * user-managed ones the settings screens list and edit; MODULE rows are owned
 * by an installed module and stay out of both the listing and the name
 * uniqueness rule.
 */
class TaxType extends Model
{
    use HasFactory;

    public const TYPE_GENERAL = 'GENERAL';

    public const TYPE_MODULE = 'MODULE';

    public const TRANSACTION_TYPE_SALES = 'sales';

    public const TRANSACTION_TYPE_PURCHASES = 'purchases';

    protected $table = 'tax_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'percent' => 'float',
            'fixed_amount' => 'integer',
            'compound_tax' => 'boolean',
        ];
    }

    /**
     * EN 16931 Tax Category Codes (UNTDID 5305 subset) classifying a tax rate.
     */
    public const TAX_CATEGORY_CODE_STANDARD = 'S';

    public const TAX_CATEGORY_CODE_ZERO_RATED = 'Z';

    public const TAX_CATEGORY_CODE_EXEMPT = 'E';

    public const TAX_CATEGORY_CODE_REVERSE_CHARGE = 'AE';

    public const TAX_CATEGORY_CODE_INTRA_COMMUNITY = 'K';

    public const TAX_CATEGORY_CODE_FREE_EXPORT = 'G';

    public const TAX_CATEGORY_CODE_OUTSIDE_SCOPE = 'O';

    public const TAX_CATEGORY_CODE_CANARY_ISLANDS = 'L';

    public const TAX_CATEGORY_CODE_CEUTA_MELILLA = 'M';

    /**
     * Every Tax Category Code the data model accepts.
     */
    public const TAX_CATEGORY_CODES = [
        self::TAX_CATEGORY_CODE_STANDARD,
        self::TAX_CATEGORY_CODE_ZERO_RATED,
        self::TAX_CATEGORY_CODE_EXEMPT,
        self::TAX_CATEGORY_CODE_REVERSE_CHARGE,
        self::TAX_CATEGORY_CODE_INTRA_COMMUNITY,
        self::TAX_CATEGORY_CODE_FREE_EXPORT,
        self::TAX_CATEGORY_CODE_OUTSIDE_SCOPE,
        self::TAX_CATEGORY_CODE_CANARY_ISLANDS,
        self::TAX_CATEGORY_CODE_CEUTA_MELILLA,
    ];

    /**
     * Tax Category Codes whose EN 16931 business rules demand an exemption
     * reason text (BT-120), e.g. § 19 UStG for the exempt category.
     */
    public const EXEMPT_TAX_CATEGORY_CODES = [
        self::TAX_CATEGORY_CODE_EXEMPT,
        self::TAX_CATEGORY_CODE_REVERSE_CHARGE,
        self::TAX_CATEGORY_CODE_INTRA_COMMUNITY,
        self::TAX_CATEGORY_CODE_FREE_EXPORT,
        self::TAX_CATEGORY_CODE_OUTSIDE_SCOPE,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Every applied-tax row snapshotted from this type. Non-empty means the
     * type is pinned: deletion is refused while any of these exist.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    /**
     * Translate the listing's query string into query conditions.
     *
     * The order matters for the id filter: `whereTaxType` contributes an OR
     * clause, and it is only harmless because it lands first, where Eloquent
     * strips the leading boolean. Everything after it ANDs as expected.
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('tax_type_id')) {
            $query->whereTaxType($filters->get('tax_type_id'));
        }

        if ($filters->get('company_id')) {
            // The submitted id is not honoured -- the scope reads the company
            // from the request header. The filter merely switches it on.
            $query->whereCompany();
        }

        if ($filters->get('transaction_type')) {
            $query->whereTransactionType($filters->get('transaction_type'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            // 'payment_number' is not a column on this table: a legacy
            // carry-over from the payments listing, kept as-is.
            $query->whereOrder(
                $filters->get('orderByField') ?: 'payment_number',
                $filters->get('orderBy') ?: 'asc'
            );
        }
    }

    /**
     * Scope to the company the request is acting in. Any argument passed by a
     * caller is deliberately ignored -- the header is the only source.
     */
    public function scopeWhereCompany(Builder $query): void
    {
        $query->where('company_id', request()->header('company'));
    }

    public function scopeWhereTaxType(Builder $query, int $tax_type_id): void
    {
        $query->orWhere('id', $tax_type_id);
    }

    public function scopeWhereTransactionType(Builder $query, string $transaction_type): void
    {
        $query->where('transaction_type', $transaction_type);
    }

    public function scopeWhereSearch(Builder $query, string $search): void
    {
        $query->where('name', 'LIKE', '%'.$search.'%');
    }

    /**
     * User-supplied sort input, sanitised before it reaches the ORDER BY.
     */
    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): void
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    /**
     * `limit=all` opts out of pagination and returns the plain collection.
     *
     * @return Collection|LengthAwarePaginator
     */
    public function scopePaginateData(Builder $query, string $limit)
    {
        return $limit === 'all' ? $query->get() : $query->paginate($limit);
    }
}
