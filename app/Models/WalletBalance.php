<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletBalance extends Model
{
    protected $fillable = [
        'wallet_id',
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

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
