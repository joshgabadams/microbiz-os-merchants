<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAgreementSignatory extends Model
{
    protected $fillable = [
        'agent_agreement_id',
        'party',
        'signatory_name',
        'signatory_title',
        'signature_method',
        'signature_evidence_path',
        'provider_reference_id',
        'signed_at',
        'ip_address',
        'recorded_by',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(AgentAgreement::class, 'agent_agreement_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
