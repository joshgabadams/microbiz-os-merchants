<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentDocument extends Model
{
    protected $fillable = [
        'agent_id',
        'document_type',
        'document_number',
        'storage_path',
        'issued_at',
        'expires_at',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
