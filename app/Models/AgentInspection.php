<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentInspection extends Model
{
    protected $fillable = [
        'inspection_no',

        'agent_id',
        'agent_location_id',
        'inspector_id',

        'inspection_type',
        'inspection_date',

        'status',

        'findings',
        'compliance_outcome',

        'corrective_action',
        'corrective_action_deadline',

        'follow_up_status',
        'follow_up_notes',

        'created_by',

        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'corrective_action_deadline' => 'date',

        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function location()
    {
        return $this->belongsTo(
            AgentLocation::class,
            'agent_location_id'
        );
    }

    public function inspector()
    {
        return $this->belongsTo(
            User::class,
            'inspector_id'
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
