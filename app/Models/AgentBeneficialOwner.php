<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBeneficialOwner extends Model
{
    protected $fillable = [
        'agent_id',
        'full_name',
        'date_of_birth',
        'nationality',
        'identification_type',
        'identification_number',
        'ownership_percentage',
        'is_director',
        'is_pep',
        'sanctions_match',
        'screening_status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_director' => 'boolean',
        'is_pep' => 'boolean',
        'sanctions_match' => 'boolean',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
