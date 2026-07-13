<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TellerBalance extends Model
{
    protected $fillable = [

        'teller_id',

        'currency',

        'ledger_balance',

        'available_balance',

        'locked_balance',

        'last_transaction_id'

    ];

    protected $casts = [

        'ledger_balance' => 'decimal:2',

        'available_balance' => 'decimal:2',

        'locked_balance' => 'decimal:2',

    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function teller()
    {
        return $this->belongsTo(Teller::class);
    }

    public function lastTransaction()
    {
        return $this->belongsTo(
            TellerTransaction::class,
            'last_transaction_id'
        );
    }

    public function transactions()
{
    return $this->hasMany(TellerTransaction::class);
}
}