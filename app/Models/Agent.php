<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_code',
        'agent_type',
        'legal_name',
        'trading_name',
        'registration_number',
        'tax_identification_number',
        'bvn',
        'phone',
        'email',
        'branch_id',
        'supervisor_id',
        'status',
        'kyc_status',
        'risk_rating',
        'exclusive_relationship',
        'principal_reference',
        'daily_transaction_limit',
        'daily_cash_out_limit',
        'single_transaction_limit',
        'next_review_date',
        'created_by',
        'approved_by',
        'approved_at',
        'activated_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'exclusive_relationship' => 'boolean',
        'next_review_date' => 'date',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function owners()
    {
        return $this->hasMany(AgentBeneficialOwner::class);
    }

    public function documents()
    {
        return $this->hasMany(AgentDocument::class);
    }


    public function locations()
{
    return $this->hasMany(AgentLocation::class);
}

public function agreements()
{
    return $this->hasMany(AgentAgreement::class);
}

public function activeAgreement()
{
    return $this->hasOne(AgentAgreement::class)
        ->where('status', 'ACTIVE')
        ->latestOfMany();
}

public function verifiedLocations()
{
    return $this->hasMany(AgentLocation::class)
        ->where('verification_status', 'VERIFIED');
}

public function operators()
{
    return $this->hasMany(AgentOperator::class);
}

public function terminals()
{
    return $this->hasMany(AgentTerminal::class);
}

public function trainingRecords()
{
    return $this->hasMany(AgentTrainingRecord::class);
}


}
