<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaultBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_id',
        'currency',
        'ledger_balance',
        'available_balance',
        'locked_balance',
        'last_transaction_id',
    ];

    protected $casts = [
        'ledger_balance'    => 'decimal:2',
        'available_balance' => 'decimal:2',
        'locked_balance'    => 'decimal:2',
    ];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }
}