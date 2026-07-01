<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultTransaction extends Model
{
    protected $fillable = [

        'vault_id',

        'transaction_no',

        'transaction_type',

        'amount',

        'currency',

        'reference',

        'narration',

        'performed_by',

        'approved_by',

        'transaction_date',

        'posted'

    ];

    protected $casts = [

        'amount'=>'decimal:2',

        'posted'=>'boolean',

        'transaction_date'=>'datetime'

    ];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class,'performed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class,'approved_by');
    }
}