<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'customer_no',
        'first_name',
        'last_name',
        'phone',
        'email',
        'status',
    ];

    public function accounts()
    {
        return $this->hasMany(CustomerAccount::class);
    }
}