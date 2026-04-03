<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $dates = [
        'date_answer',
        'date_time_answer',
    ];

    protected $appends = [
        'defaultAnswer',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function setTimeAnswerAttribute($value)
    {
        if ($value && $value != null) {
            $this->attributes['time_answer'] = date('H:i:s', strtotime($value));
        }
    }

    public function setOptionsAttribute($value)
    {
        $this->attributes['options'] = json_encode($value);
    }

    public function getDefaultAnswerAttribute()
    {
        $value_type = getCustomFieldValueKey($this->type);

        return $this->$value_type;
    }

    public function getInUseAttribute()
    {
        return $this->customFieldValues()->exists();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function scopeWhereCompany($query)
    {
        return $query->where('custom_fields.company_id', request()->header('company'));
    }

    public function scopeWhereSearch($query, $search)
    {
        $query->where(function ($query) use ($search) {
            $query->where('label', 'LIKE', '%'.$search.'%')
                ->orWhere('name', 'LIKE', '%'.$search.'%');
        });
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('type')) {
            $query->whereType($filters->get('type'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }
    }

    public function scopeWhereType($query, $type)
    {
        $query->where('custom_fields.model_type', $type);
    }
}
