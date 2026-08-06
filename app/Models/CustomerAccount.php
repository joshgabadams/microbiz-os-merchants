<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'account_no',
        'account_type',
        'product_code',
        'currency',
        'gl_account_id',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function balance()
    {
        return $this->hasOne(CustomerAccountBalance::class);
    }

    public function transactions()
    {
        return $this->hasMany(CustomerAccountTransaction::class);
    }
}
