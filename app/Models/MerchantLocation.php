<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantLocation extends Model
{
    protected $fillable = [
        'merchant_id',
        'branch_id',
        'location_code',
        'name',
        'trading_name',
        'address',
        'state',
        'local_government',
        'latitude',
        'longitude',
        'contact_person',
        'operating_hours',
        'risk_classification',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
