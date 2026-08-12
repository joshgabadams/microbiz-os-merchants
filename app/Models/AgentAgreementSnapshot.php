<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAgreementSnapshot extends Model
{
    protected $fillable = [
        'agent_agreement_id',
        'snapshot_type',
        'source_id',
        'snapshot_data',
        'snapshotted_at',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'snapshotted_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(AgentAgreement::class, 'agent_agreement_id');
    }
}
