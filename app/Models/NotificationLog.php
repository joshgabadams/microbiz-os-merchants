<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'notifiable_type', 'notifiable_id', 'channel', 'template',
        'status', 'failure_reason', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
