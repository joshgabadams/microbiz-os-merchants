<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashLedger extends Model
{
    protected $fillable = [
        'reference_no',

        'branch_id',
        'vault_id',
        'teller_id',
        'user_id',

        'transaction_type',   // CASH_IN, CASH_OUT, etc.
        'source_type',        // TillTransaction, VaultTransfer, etc.
        'source_id',

        'entry_type',         // DEBIT | CREDIT

        'account_type',       // TELLER_CASH, VAULT_CASH, CUSTOMER_LIABILITY
        'account_code',       // GL mapping code (IMPORTANT for next step)

        'debit',
        'credit',

        'debit_account_key',
        'credit_account_key',

        'running_balance',

        'currency',

        'narration',

        'status',             // PENDING, POSTED, REVERSED
        'approved_by',

        'transaction_date'
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
         'running_balance' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function teller()
    {
        return $this->belongsTo(Teller::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}