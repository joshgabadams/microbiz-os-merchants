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
        'legal_name',
        'trading_name',
        'business_type',
        'registration_number',
        'tax_identification_number',
        'bvn',
        'merchant_category_code',
        'industry',
        'website',
        'state',
        'local_government',
        'registered_address',
        'operating_address',
        'risk_rating',
        'customer_type',
        'geographic_reach',
        'business_model',
        'expected_monthly_volume',
        'expected_monthly_value',
        'settlement_frequency',
        'settlement_delay_days',
        'daily_limit',
        'monthly_limit',
        'kyc_status',
        'kyc_completed_at',
        'next_review_at',
        'suspension_reason',
        'suspended_at',
        'source_channel',
    ];

    protected $casts = [
        'expected_monthly_volume' => 'decimal:2',
        'expected_monthly_value' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
        'kyc_completed_at' => 'datetime',
        'next_review_at' => 'datetime',
        'suspended_at' => 'datetime',
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

    public function owners()
    {
        return $this->hasMany(MerchantBeneficialOwner::class);
    }

    public function documents()
    {
        return $this->hasMany(MerchantDocument::class);
    }

    public function locations()
    {
        return $this->hasMany(MerchantLocation::class);
    }

    public function terminals()
    {
        return $this->hasMany(MerchantTerminal::class);
    }
}
