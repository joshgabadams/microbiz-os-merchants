<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentReconciliation extends Model
{
    protected $fillable = [
        'agent_id',
        'branch_id',

        'system_expected_physical_cash',
        'declared_physical_cash',
        'variance',

        'status', // PENDING, MATCHED, MISMATCHED, APPROVED

        'reconciled_by',
        'approved_by',

        'notes',
        'reconciled_at',
    ];

    protected $casts = [
        'system_expected_physical_cash' => 'decimal:2',
        'declared_physical_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function reconciledBy()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
