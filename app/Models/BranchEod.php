<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchEod extends Model
{
    protected $fillable = [
        'branch_id',
        'business_date',
        'status',
        'total_tellers',
        'closed_tellers',
        'balanced_tellers',
        'vault_balanced',
        'has_pending_approvals',
        'has_unposted_transactions',
        'closed_by',
        'closed_at',
        'note',
    ];

    protected $casts = [
        'business_date' => 'date',
        'vault_balanced' => 'boolean',
        'has_pending_approvals' => 'boolean',
        'has_unposted_transactions' => 'boolean',
        'closed_at' => 'datetime',
    ];
}