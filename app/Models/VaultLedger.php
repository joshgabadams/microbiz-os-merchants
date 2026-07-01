<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultLedger extends Model
{
    protected $fillable = [
        'vault_id',
        'vault_transaction_id',
        'transaction_no',
        'transaction_date',
        'entry_type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'reference',
        'narration',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Vault.
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /**
     * Source vault transaction.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            VaultTransaction::class,
            'vault_transaction_id'
        );
    }

    /**
     * User who created the ledger entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}