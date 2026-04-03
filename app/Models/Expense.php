<?php

namespace App\Models;

use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Expense extends Model implements HasMedia
{
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    protected $dates = [
        'expense_date',
    ];

    protected $guarded = ['id'];

    protected $appends = [
        'formattedExpenseDate',
        'formattedCreatedAt',
        'receipt',
        'receiptMeta',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'string',
            'exchange_rate' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getFormattedExpenseDateAttribute(mixed $value): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->expense_date)->translatedFormat($dateFormat);
    }

    public function getFormattedCreatedAtAttribute(mixed $value): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->translatedFormat($dateFormat);
    }

    public function getReceiptUrlAttribute(mixed $value): ?array
    {
        $media = $this->getFirstMedia('receipts');

        if ($media) {
            return [
                'url' => $media->getFullUrl(),
                'type' => $media->type,
            ];
        }

        return null;
    }

    public function getReceiptAttribute(mixed $value): ?string
    {
        $media = $this->getFirstMedia('receipts');

        if ($media) {
            return $media->getPath();
        }

        return null;
    }

    public function getReceiptMetaAttribute(mixed $value): ?Media
    {
        $media = $this->getFirstMedia('receipts');

        if ($media) {
            return $media;
        }

        return null;
    }

    public function scopeExpensesBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween(
            'expenses.expense_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereCategoryName(Builder $query, string $search): void
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('category', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeWhereNotes(Builder $query, string $search): void
    {
        $query->where('notes', 'LIKE', '%'.$search.'%');
    }

    public function scopeWhereCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('expenses.expense_category_id', $categoryId);
    }

    public function scopeWhereUser(Builder $query, int $customer_id): Builder
    {
        return $query->where('expenses.customer_id', $customer_id);
    }

    /**
     * Apply multiple filter conditions including category, customer, expense, date range, ordering, and search.
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('expense_category_id')) {
            $query->whereCategory($filters->get('expense_category_id'));
        }

        if ($filters->get('customer_id')) {
            $query->whereUser($filters->get('customer_id'));
        }

        if ($filters->get('expense_id')) {
            $query->whereExpense($filters->get('expense_id'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->expensesBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'expense_date';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }
    }

    public function scopeWhereExpense(Builder $query, int $expense_id): void
    {
        $query->orWhere('id', $expense_id);
    }

    public function scopeWhereSearch(Builder $query, string $search): void
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('category', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%');
            })
                ->orWhere('notes', 'LIKE', '%'.$term.'%');
        }
    }

    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): void
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereCompany(Builder $query): void
    {
        $query->where('expenses.company_id', request()->header('company'));
    }

    public function scopeWhereCompanyId(Builder $query, int $company): void
    {
        $query->where('expenses.company_id', $company);
    }

    /**
     * @return LengthAwarePaginator|Collection
     */
    public function scopePaginateData(Builder $query, string $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeExpensesAttributes(Builder $query): void
    {
        $query->select(
            DB::raw('
                count(*) as expenses_count,
                sum(base_amount) as total_amount,
                expense_category_id')
        )
            ->groupBy('expense_category_id');
    }
}
