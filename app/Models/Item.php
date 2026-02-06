<?php

namespace App\Models;

use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Item extends Model
{
    use HasCustomFieldsTrait;
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'custom_fields' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeWhereSearch($query, $search)
    {
        return $query->where('items.name', 'LIKE', '%'.$search.'%');
    }

    public function scopeWherePrice($query, $price)
    {
        return $query->where('items.price', $price);
    }

    public function scopeWhereUnit($query, $unit_id)
    {
        return $query->where('items.unit_id', $unit_id);
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereItem($query, $item_id)
    {
        $query->orWhere('id', $item_id);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('price')) {
            $query->wherePrice($filters->get('price'));
        }

        if ($filters->get('unit_id')) {
            $query->whereUnit($filters->get('unit_id'));
        }

        if ($filters->get('item_id')) {
            $query->whereItem($filters->get('item_id'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'name';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', request()->header('company'));

        return Carbon::parse($this->created_at)->translatedFormat($dateFormat);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class)
            ->where('invoice_item_id', null)
            ->where('estimate_item_id', null);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('items.company_id', request()->header('company'));
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function estimateItems(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }

    public static function createItem($request)
    {
        $data = $request->validated();
        $data['company_id'] = $request->header('company');
        $data['creator_id'] = Auth::id();
        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));
        $data['currency_id'] = $company_currency;
        $item = self::create($data);

        $customFields = $request->customFields;
        if ($customFields) {
            $item->addCustomFields($customFields);
        }

        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                $item->tax_per_item = true;
                $item->save();
                $tax['company_id'] = $request->header('company');
                $item->taxes()->create($tax);
            }
        }

        $item = self::with('taxes')->find($item->id);

        return $item;
    }

    public function updateItem($request)
    {
        $this->update($request->validated());

        $this->taxes()->delete();

        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                $this->tax_per_item = true;
                $this->save();
                $tax['company_id'] = $request->header('company');
                $this->taxes()->create($tax);
            }
        }

        // Handle custom fields
        $customFields = $request->customFields;
        if ($customFields) {
            $this->updateCustomFields($customFields);
        }

        return Item::with('taxes')->find($this->id);
    }

    public function getExtraFields()
    {
        $fields = [
            '{ITEM_NAME}' => $this->name,
            '{ITEM_PRICE}' => $this->price,
            '{ITEM_UNIT}' => $this->unit ? $this->unit->name : null,
            '{ITEM_DESCRIPTION}' => $this->description,
            '{ITEM_SKU}' => $this->sku,
        ];

        // Dynamically add custom fields
        if (! empty($this->custom_fields) && is_array($this->custom_fields)) {
            foreach ($this->custom_fields as $key => $value) {
                // Use a consistent placeholder format, e.g. {ITEM_CUSTOM_FIELD_fieldname}
                $fields['{ITEM_CUSTOM_FIELD_'.strtoupper($key).'}'] = $value;
            }
        }

        return $fields;
    }
}
