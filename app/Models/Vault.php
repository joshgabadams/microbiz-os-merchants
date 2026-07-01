<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'gl_account_id',

        'code',
        'name',
        'type',
        'currency',

        'minimum_balance',
        'maximum_balance',

        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'minimum_balance' => 'decimal:2',
        'maximum_balance' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(
            GlAccount::class,
            'gl_account_id'
        );
    }

    public function balances()
    {
        return $this->hasMany(VaultBalance::class);
    }

    public function balance()
{
    return $this->hasOne(VaultBalance::class)
        ->where('currency', $this->currency);
}

    public function transactions()
    {
        return $this->hasMany(VaultTransaction::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(
            VaultTransfer::class,
            'source_vault_id'
        );
    }

    public function incomingTransfers()
    {
        return $this->hasMany(
            VaultTransfer::class,
            'destination_vault_id'
        );
    }
}
