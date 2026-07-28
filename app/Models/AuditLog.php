<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // created_at only, set explicitly

    protected $fillable = [
        'actor_id', 'actor_label',
        'subject_type', 'subject_id',
        'action', 'module',
        'before', 'after', 'metadata',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
