<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TillSession extends Model
{
    protected $fillable = [
        'session_reference',
        'teller_id',
        'branch_id',
        'vault_id',
        'opened_by',
        'closed_by',
        'opening_float',
        'current_balance',
        'expected_cash',
        'physical_cash',
        'variance',
        'status',
        'opened_at',
        'closed_at'
    ];

    protected $casts = [
        'opening_float'   => 'decimal:2',
        'current_balance' => 'decimal:2',
        'expected_cash'   => 'decimal:2',
        'physical_cash'   => 'decimal:2',
        'variance'        => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'status'          => 'string',
    ];

    // relationships
    public function teller()
    {
        return $this->belongsTo(Teller::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transactions()
    {
        return $this->hasMany(TillTransaction::class);
    }
}