<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTrainingRecord extends Model
{
    protected $fillable = [
        'agent_id',
        'operator_id',
        'training_document_id',
        'training_document_version',
        'downloaded_at',
        'acknowledged_at',
        'acknowledged_by',
        'recorded_by',
        'ip_address',
        'completion_method',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function operator()
    {
        return $this->belongsTo(AgentOperator::class, 'operator_id');
    }

    public function trainingDocument()
    {
        return $this->belongsTo(TrainingDocument::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
