<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'wallet_no',
        'owner_name',
        'phone',
        'bvn',
        'nin',
        'kyc_tier',
        'status',
        'onboarded_by',
    ];

    protected $hidden = [
        'bvn',
        'nin',
    ];

    protected $casts = [
        'kyc_tier' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function onboardedBy()
    {
        return $this->belongsTo(User::class, 'onboarded_by');
    }

    public function balance()
    {
        return $this->hasOne(WalletBalance::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
