<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TellerCashBalance extends Model
{
    protected $fillable = [
        'teller_id',
        'business_date',
        'opening_cash',
        'float_received',
        'customer_deposits',
        'customer_withdrawals',
        'float_returned',
        'expected_cash',
        'physical_cash',
        'variance',
        'status',
        'balanced_by',
        'balanced_at',
        'note',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opening_cash' => 'decimal:2',
        'float_received' => 'decimal:2',
        'customer_deposits' => 'decimal:2',
        'customer_withdrawals' => 'decimal:2',
        'float_returned' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'physical_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'balanced_at' => 'datetime',
    ];

    public function teller()
    {
        return $this->belongsTo(Teller::class);
    }
}
