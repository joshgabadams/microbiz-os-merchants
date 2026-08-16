<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentComplaint extends Model
{
    protected $fillable = [
        'complaint_no',

        'agent_id',
        'branch_id',
        'agent_location_id',
        'agent_transaction_id',

        'complainant_name',
        'complainant_phone',
        'complainant_email',

        'channel',
        'category',
        'subject',
        'description',
        'disputed_amount',

        'priority',
        'status',

        'assigned_to',
        'created_by',

        'acknowledged_at',
        'due_at',

        'resolution_summary',
        'resolved_at',
        'closed_at',

        'escalation_level',
        'escalation_reason',
        'escalated_at',
    ];

    protected $casts = [
        'disputed_amount' => 'decimal:2',

        'acknowledged_at' => 'datetime',
        'due_at' => 'datetime',

        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function location()
    {
        return $this->belongsTo(
            AgentLocation::class,
            'agent_location_id'
        );
    }

    public function transaction()
    {
        return $this->belongsTo(
            AgentTransaction::class,
            'agent_transaction_id'
        );
    }

    public function assignedTo()
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function createdBy()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
