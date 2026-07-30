<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantBalance extends Model
{
    protected $fillable = [
        'merchant_id',
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

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
