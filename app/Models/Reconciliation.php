<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $fillable = [
        'till_session_id',
        'teller_id',
        'branch_id',

        'system_balance',
        'physical_cash',
        'variance',

        'status', // PENDING, MATCHED, MISMATCHED, APPROVED

        'reconciled_by',
        'approved_by',

        'notes',
        'reconciled_at'
    ];

    protected $casts = [
        'system_balance' => 'decimal:2',
        'physical_cash'  => 'decimal:2',
        'variance'       => 'decimal:2',
        'reconciled_at'  => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(TillSession::class, 'till_session_id');
    }

    public function teller()
    {
        return $this->belongsTo(Teller::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}