<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTransaction extends Model
{
    protected $fillable = [

        'merchant_id',

        'transaction_no',

        'transaction_type',

        'amount',

        'currency',

        'reference',

        'narration',

        'performed_by',

        'approved_by',

        'transaction_date',

        'posted',

        'reversal_of_transaction_id',
        'is_reversed',
        'reversed_at',
        'reversed_by',

    ];

    protected $casts = [

        'amount' => 'decimal:2',

        'transaction_date' => 'datetime',

        'posted' => 'boolean',

        'is_reversed' => 'boolean',
        'reversed_at' => 'datetime',

    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
