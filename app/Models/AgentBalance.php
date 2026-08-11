<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBalance extends Model
{
    protected $fillable = [
        'agent_id',
        'currency',
        'ledger_float',
        'available_float',
        'locked_float',
        'declared_physical_cash',
        'commission_balance',
        'pending_commission',
        'last_transaction_id',
    ];

    protected $casts = [
        'ledger_float' => 'decimal:2',
        'available_float' => 'decimal:2',
        'locked_float' => 'decimal:2',
        'declared_physical_cash' => 'decimal:2',
        'commission_balance' => 'decimal:2',
        'pending_commission' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
