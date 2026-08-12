<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentService extends Model
{
    protected $fillable = [
        'agent_id',
        'service_type',
        'status',
        'limit_override',
        'fee_amount',
        'authentication_required',
        'effective_date',
        'expiry_date',
        'enabled_by',
        'enabled_at',
    ];

    protected $casts = [
        'limit_override' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'authentication_required' => 'boolean',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'enabled_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function enabledBy()
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}
