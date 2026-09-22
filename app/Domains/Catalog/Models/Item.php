<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Models\EstimateItem;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sellable entry in a company's catalogue: a name, an optional
 * description, and a price kept in minor currency units.
 *
 * The currency is not part of the payload -- it is stamped at creation from
 * the company's base-currency setting, so every price in a catalogue is
 * denominated the same way. An item may additionally carry its own default
 * taxes (see taxes()), which serve as the template copied onto a document
 * line whenever the item is put on an invoice or an estimate.
 */
class Item extends Model
{
    use HasCustomFields;
    use HasFactory;

    protected $table = 'items';

    protected $guarded = ['id'];

    /**
     * The rendered creation date rides along with every serialised item; the
     * accessor below explains where the format comes from.
     */
    protected $appends = [
        'formattedCreatedAt',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    /**
     * Whoever added the item. Attribution only -- it confers no access.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    /**
     * The currency the price is expressed in, taken from the company setting
     * at creation time and left alone afterwards.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    /**
     * The optional measure the item is sold by. Unitless items are ordinary:
     * the column is nullable and the listing simply reports no unit for them.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    /**
     * The item's own default taxes.
     *
     * Item taxes and document-line taxes share one table, and what marks a
     * row as the item's own is the absence of a document parent. Both parent
     * columns have to be excluded: a row pointing at an invoice line is that
     * line's tax, not a default of the item it was created from.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'item_id', 'id')
            ->whereNull('invoice_item_id')
            ->whereNull('estimate_item_id');
    }

    /**
     * Invoice lines drawn from this item; their existence pins it against
     * deletion.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'item_id', 'id');
    }

    /**
     * Estimate lines drawn from this item; likewise a bar to deletion.
     */
    public function estimateItems(): HasMany
    {
        return $this->hasMany(EstimateItem::class, 'item_id', 'id');
    }

    /**
     * Turn the listing's query string into query conditions.
     *
     * Every filter is switched on by a truthy value, so a zero -- a price
     * filter of 0, say -- reads as "not filtering" and is skipped.
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($search = $filters->get('search')) {
            $query->whereSearch($search);
        }

        if ($price = $filters->get('price')) {
            $query->wherePrice($price);
        }

        if ($unitId = $filters->get('unit_id')) {
            $query->whereUnit($unitId);
        }

        if ($itemId = $filters->get('item_id')) {
            // Applied last, and an OR: it widens the result set instead of
            // narrowing it, reaching past every condition already on the
            // query. Long-standing behaviour of the id filter, kept as is.
            $query->whereItem($itemId);
        }

        $sortField = $filters->get('orderByField');
        $sortDirection = $filters->get('orderBy');

        // Either half of the sort pair is enough to switch sorting on; the
        // half that was left out falls back to ascending by name.
        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'name', $sortDirection ?: 'asc');
        }
    }

    /**
     * Restrict to the company the request is acting in.
     *
     * Columns are table-qualified across these scopes because the listing
     * joins `units`, which carries columns of the same names.
     */
    public function scopeWhereCompany(Builder $query): void
    {
        $company = request()->header('company');

        $query->where($this->qualifyColumn('company_id'), $company);
    }

    public function scopeWhereSearch(Builder $query, string $search): Builder
    {
        $pattern = '%'.$search.'%';

        return $query->where($this->qualifyColumn('name'), 'LIKE', $pattern);
    }

    /**
     * Exact match on the stored price, in minor units.
     */
    public function scopeWherePrice(Builder $query, int $price): Builder
    {
        return $query->where($this->qualifyColumn('price'), $price);
    }

    public function scopeWhereUnit(Builder $query, int $unit_id): Builder
    {
        return $query->where($this->qualifyColumn('unit_id'), $unit_id);
    }

    /**
     * Pull one specific item into the result set -- see the OR note in
     * applyFilters(). The column is left unqualified here.
     */
    public function scopeWhereItem(Builder $query, int $item_id): void
    {
        $query->orWhere('id', '=', $item_id);
    }

    /**
     * Sort input arrives from the query string, so it goes through the
     * sanitiser; anything that is not a plain column name is replaced with
     * the fallback below.
     */
    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): void
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy, 'created_at');
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

    /**
     * The creation date rendered with a company's date-format setting, so a
     * client never has to know the setting itself.
     *
     * The format is read for the company the request is acting in rather than
     * the item's own company; for cross-company reads the two can differ.
     */
    public function getFormattedCreatedAtAttribute(mixed $value): string
    {
        $company = request()->header('company');

        return Carbon::parse($this->created_at)
            ->translatedFormat(CompanySetting::getSetting('carbon_date_format', $company));
    }
}
