<?php

namespace InvoiceShelf\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['title','start','end','description','user_id','company_id','customer_id','installer_id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function installer()
    {
        return $this->belongsTo(Installer::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
