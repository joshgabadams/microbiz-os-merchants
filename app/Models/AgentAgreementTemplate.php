<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAgreementTemplate extends Model
{
    protected $fillable = [
        'name',
        'version',
        'status',
        'legal_clauses',
        'default_operator_training_obligations',
        'default_escalation_contacts',
        'governing_law',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'default_escalation_contacts' => 'array',
        'approved_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function agreements()
    {
        return $this->hasMany(AgentAgreement::class, 'agreement_template_id');
    }
}
