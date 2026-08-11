<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentOperator extends Model
{
    protected $fillable = [
        'agent_id',
        'agent_location_id',
        'user_id',
        'role',
        'status',
        'training_completed_at',
        'activated_at',
        'suspended_at',
    ];

    protected $casts = [
        'training_completed_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function location()
    {
        return $this->belongsTo(AgentLocation::class, 'agent_location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
