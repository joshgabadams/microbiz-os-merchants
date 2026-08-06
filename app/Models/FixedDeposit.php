<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDeposit extends Model
{
    protected $fillable = [
        'fd_no',
        'customer_account_id',
        'settlement_account_id',
        'principal_amount',
        'currency',
        'interest_rate',
        'pre_liquidation_rate',
        'pre_liquidation_penalty_fee',
        'tenor_days',
        'start_date',
        'backdate_reason',
        'maturity_date',
        'status',
        'booked_by',
        'liquidated_by',
        'liquidated_at',
        'interest_paid',
        'narration',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'pre_liquidation_rate' => 'decimal:4',
        'pre_liquidation_penalty_fee' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'liquidated_at' => 'datetime',
    ];

    public function sourceAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function settlementAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'settlement_account_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function liquidatedBy()
    {
        return $this->belongsTo(User::class, 'liquidated_by');
    }
}
