<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAccountBalance extends Model
{
    protected $fillable = [
        'customer_account_id',
        'currency',
        'ledger_balance',
        'available_balance',
        'locked_balance',
        'last_transaction_id',
    ];

    protected $casts = [
        'ledger_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'locked_balance' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}