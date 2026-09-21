<?php

namespace App\Domains\Metadata\Models;

use App\Domains\Accounts\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An extra question a company attaches to one of its record types.
 *
 * The definition holds the question -- label, input type, option list,
 * placeholder, position in the form, whether an answer is compulsory -- and
 * the answer to fall back on when none is given. That fallback is kept in
 * whichever of the six typed columns the input type maps to, exactly the way
 * a record's own answer is, so a single mapping helper serves both.
 *
 * One thing is looser than it looks: the slug a template addresses the field
 * by is stamped once, at creation, and never recomputed, so renaming a field
 * leaves every placeholder that already names it working.
 */
class CustomField extends Model
{
    /** Answered and read in the interface, never printed. */
    public const PLACEMENT_INTERNAL = 'internal';

    /** Printed on the document the record belongs to. */
    public const PLACEMENT_DOCUMENT = 'document';

    protected $table = 'custom_fields';

    use HasFactory;

    /**
     * Everything but the key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Inert. The framework stopped reading this property, so the two columns
     * named here are handed out as the plain strings the driver returns, not
     * as date objects. Kept because it is part of the class as published.
     *
     * @var array
     */
    protected $dates = [
        'date_answer',
        'date_time_answer',
    ];

    /**
     * The fallback answer travels with every serialized definition, under the
     * camel-cased name it is computed from.
     *
     * @var array
     */
    protected $appends = [
        'defaultAnswer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    /**
     * Reduce a time-of-day fallback to H:i:s.
     *
     * An empty value is written through as null so the fallback can be
     * cleared, matching the answer model's copy of this mutator. A value the
     * parser cannot read becomes midnight rather than an error.
     */
    public function setTimeAnswerAttribute(mixed $value): void
    {
        $this->attributes['time_answer'] = $value ? date('H:i:s', strtotime($value)) : null;
    }

    /**
     * The fallback answer, read from the column this field's input type maps
     * to. A type outside the mapping reads the string column.
     */
    public function getDefaultAnswerAttribute()
    {
        $answerColumn = getCustomFieldValueKey($this->type);

        return $this->{$answerColumn};
    }

    /**
     * Whether any record has an answer on file for this field.
     *
     * Serialized with the definition so the interface can warn before a
     * delete; nothing on the delete path itself consults it.
     */
    public function getInUseAttribute()
    {
        return $this->customFieldValues()->exists();
    }

    /**
     * The company the field was defined in.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Every answer recorded against this field, whatever record type holds it.
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_id');
    }

    /**
     * Narrow to the company the current request is acting on.
     *
     * The company is read from the request header; the scope takes no
     * argument and cannot be pointed at a different company.
     */
    public function scopeWhereCompany($query)
    {
        $company = request()->header('company');

        return $query->where('custom_fields.company_id', $company);
    }

    /**
     * Partial match on either name the field goes by, grouped so it stays one
     * condition when it is combined with others.
     */
    public function scopeWhereSearch($query, $search)
    {
        $needle = '%'.$search.'%';

        $query->where(function ($grouped) use ($needle) {
            $grouped->where('label', 'LIKE', $needle)
                ->orWhere('name', 'LIKE', $needle);
        });
    }

    /**
     * Only the definitions meant to appear on the printed document.
     */
    public function scopeWherePrinted($query)
    {
        $query->where('custom_fields.placement', self::PLACEMENT_DOCUMENT);
    }

    /**
     * Fields attached to one record type.
     */
    public function scopeWhereType($query, $type)
    {
        $query->where('custom_fields.model_type', $type);
    }

    /**
     * Apply the listing filters that carry a value. An empty string, a zero
     * or a null counts as a filter that was not sent.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $wanted = collect($filters);

        if ($type = $wanted->get('type')) {
            $query->whereType($type);
        }

        if ($search = $wanted->get('search')) {
            $query->whereSearch($search);
        }
    }

    /**
     * A page of the requested size, or the whole set for the sentinel limit
     * "all".
     */
    public function scopePaginateData($query, $limit)
    {
        return $limit == 'all' ? $query->get() : $query->paginate($limit);
    }
}
