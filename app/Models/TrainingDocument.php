<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingDocument extends Model
{
    protected $fillable = [
        'name',
        'version',
        'status',
        'file_path',
        'created_by',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trainingRecords()
    {
        return $this->hasMany(AgentTrainingRecord::class);
    }
}
