<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAgreementApproval extends Model
{
    protected $fillable = [
        'agent_agreement_id',
        'approval_type',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(AgentAgreement::class, 'agent_agreement_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
