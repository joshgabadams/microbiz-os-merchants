<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchBusinessDay extends Model
{
    protected $fillable = [
        'branch_id',
        'business_date',
        'status',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
