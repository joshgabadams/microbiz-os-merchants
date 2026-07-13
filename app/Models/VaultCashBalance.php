<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultCashBalance extends Model
{
    protected $fillable = [
        'vault_id',
        'business_date',
        'opening_cash',
        'vault_deposits',
        'vault_withdrawals',
        'float_allocated',
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
        'vault_deposits' => 'decimal:2',
        'vault_withdrawals' => 'decimal:2',
        'float_allocated' => 'decimal:2',
        'float_returned' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'physical_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'balanced_at' => 'datetime',
    ];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }
}