<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentLocation extends Model
{
    protected $fillable = [
        'agent_id',
        'location_code',
        'address_line_1',
        'address_line_2',
        'landmark',
        'city',
        'local_government',
        'state',
        'latitude',
        'longitude',
        'approved_radius_metres',
        'verification_status',
        'status',
        'verification_evidence',
        'created_by',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'verification_evidence' => 'array',
        'verified_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
