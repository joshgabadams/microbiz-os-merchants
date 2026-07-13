<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'request_no',
        'request_type',
        'payload',
        'amount',
        'currency',
        'status',
        'maker_id',
        'checker_id',
        'approved_at',
        'rejected_at',
        'maker_note',
        'checker_note',
        'executed_transaction_type',
        'executed_transaction_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function maker()
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    public function executedTransaction()
    {
        return $this->morphTo(
            __FUNCTION__,
            'executed_transaction_type',
            'executed_transaction_id'
        );
    }
}
