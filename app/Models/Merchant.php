<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    protected $fillable = [
        'merchant_code',
        'business_name',
        'contact_name',
        'phone',
        'email',
        'branch_id',
        'customer_account_id',
        'status',
        'onboarded_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customerAccount()
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    public function onboardedBy()
    {
        return $this->belongsTo(User::class, 'onboarded_by');
    }

    public function balance()
    {
        return $this->hasOne(MerchantBalance::class);
    }

    public function transactions()
    {
        return $this->hasMany(MerchantTransaction::class);
    }
}
