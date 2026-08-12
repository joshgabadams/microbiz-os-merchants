<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAgreement extends Model
{
    protected $fillable = [
        'agent_id',
        'agreement_template_id',
        'agreement_number',
        'version',
        'status',
        'effective_date',
        'expiry_date',
        'renewal_due_date',
        'initial_term_months',
        'agent_termination_notice_days',
        'microbiz_termination_notice_days',
        'dispute_resolution_method',
        'arbitration_seat',
        'governing_law',
        'relationship_manager_id',
        'special_conditions',
        'permitted_services',
        'commercial_terms',
        'document_path',
        'created_by',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'renewal_due_date' => 'date',
        'permitted_services' => 'array',
        'commercial_terms' => 'array',
        'executed_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function template()
    {
        return $this->belongsTo(AgentAgreementTemplate::class, 'agreement_template_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executedBy()
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function relationshipManager()
    {
        return $this->belongsTo(User::class, 'relationship_manager_id');
    }

    public function snapshots()
    {
        return $this->hasMany(AgentAgreementSnapshot::class);
    }

    public function approvals()
    {
        return $this->hasMany(AgentAgreementApproval::class);
    }

    public function signatories()
    {
        return $this->hasMany(AgentAgreementSignatory::class);
    }
}
