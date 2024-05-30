<?php

namespace InvoiceShelf\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    public function address(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}
