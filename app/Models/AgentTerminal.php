<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTerminal extends Model
{
    protected $fillable = [
        'agent_id',
        'agent_location_id',
        'terminal_id',
        'serial_number',
        'device_model',
        'provider',
        'application_version',
        'status',
        'registered_latitude',
        'registered_longitude',
        'geo_fence_radius_metres',
        'activated_at',
        'last_heartbeat_at',
        'last_transaction_at',
        'last_latitude',
        'last_longitude',
        'geo_fence_compliant',
        'assigned_by',
    ];

    protected $casts = [
        'registered_latitude' => 'decimal:7',
        'registered_longitude' => 'decimal:7',
        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
        'geo_fence_compliant' => 'boolean',
        'activated_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_transaction_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function location()
    {
        return $this->belongsTo(AgentLocation::class, 'agent_location_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
