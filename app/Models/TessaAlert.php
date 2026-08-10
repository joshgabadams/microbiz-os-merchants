<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TessaAlert extends Model
{
    protected $fillable = [
        'alert_type',
        'severity',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'metadata',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'detected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'detected_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
