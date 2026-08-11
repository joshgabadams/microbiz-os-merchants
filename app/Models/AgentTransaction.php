<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTransaction extends Model
{
    protected $fillable = [
        'transaction_no',
        'idempotency_key',
        'agent_id',
        'agent_location_id',
        'agent_terminal_id',
        'agent_operator_id',
        'transaction_type',
        'status',
        'amount',
        'fee_amount',
        'commission_amount',
        'currency',
        'customer_account_id',
        'customer_reference',
        'processor_reference',
        'channel_reference',
        'rrn',
        'latitude',
        'longitude',
        'geo_fence_passed',
        'transaction_date',
        'posted_at',
        'reversed_at',
        'performed_by',
        'approved_by',
        'risk_metadata',
        'channel_metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geo_fence_passed' => 'boolean',
        'transaction_date' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'risk_metadata' => 'array',
        'channel_metadata' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function location()
    {
        return $this->belongsTo(AgentLocation::class, 'agent_location_id');
    }

    public function terminal()
    {
        return $this->belongsTo(AgentTerminal::class, 'agent_terminal_id');
    }

    public function operator()
    {
        return $this->belongsTo(AgentOperator::class, 'agent_operator_id');
    }

    public function customerAccount()
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
