<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Accounts\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A measure a company sells by: "hour", "kg", "licence".
 *
 * Units are a small per-company lookup list -- a name, and nothing else of
 * substance. The name is unique inside the company, and a unit stays
 * un-deletable for as long as an item points at it.
 */
class Unit extends Model
{
    use HasFactory;

    /**
     * UN/ECE Rec 20 code used when no Unit Code is given: C62 = piece.
     */
    public const DEFAULT_UNIT_CODE = 'C62';

    protected $table = 'units';

    protected $attributes = [
        'unit_code' => self::DEFAULT_UNIT_CODE,
    ];

    /**
     * The company is mass-assignable because callers stamp it from the
     * request context; it is never read off the submitted payload.
     */
    protected $fillable = [
        'name',
        'unit_code',
        'company_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    /**
     * Items measured in this unit. A non-empty set pins the unit: deletion is
     * refused while any of them remain.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'unit_id', 'id');
    }

    /**
     * Turn the listing's query string into query conditions.
     *
     * A `company_id` entry merely switches the company scope on -- the value
     * submitted with it is discarded, because the scope reads the company
     * from the request header instead.
     */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        $filters = collect($filters);

        if ($search = $filters->get('search')) {
            $query->whereSearch($search);
        }

        if ($unitId = $filters->get('unit_id')) {
            $query->whereUnit($unitId);
        }

        if ($filters->get('company_id')) {
            $query->whereCompany();
        }

        return $query;
    }

    /**
     * Restrict to the company the request is acting in; callers get no say in
     * which company that is.
     */
    public function scopeWhereCompany(Builder $query): void
    {
        $company = request()->header('company');

        $query->where('company_id', $company);
    }

    /**
     * Pull one specific unit into the result set. It is an OR clause, so it
     * adds to whatever the query already matched rather than narrowing it.
     */
    public function scopeWhereUnit(Builder $query, int $unit_id): void
    {
        $query->orWhere('id', '=', $unit_id);
    }

    public function scopeWhereSearch(Builder $query, string $search): Builder
    {
        $pattern = '%'.$search.'%';

        return $query->where('name', 'LIKE', $pattern);
    }

    /**
     * `limit=all` opts out of pagination and hands back the collection.
     *
     * @return LengthAwarePaginator|Collection
     */
    public function scopePaginateData(Builder $query, string $limit)
    {
        return $limit === 'all' ? $query->get() : $query->paginate($limit);
    }
}
