<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teller extends Model
{
    protected $fillable = [
        'branch_id',
        'vault_id',
        'gl_account_id',
        'user_id',
        'teller_code',
        'staff_code',
        'display_name',
        'daily_limit',
        'opening_cash_limit',
        'minimum_cash',
        'maximum_cash',
        'active',
        'status'
    ];

    protected $casts = [
        'daily_limit' => 'decimal:2',
        'opening_cash_limit' => 'decimal:2',
        'minimum_cash' => 'decimal:2',
        'maximum_cash' => 'decimal:2',
        'active' => 'boolean',
        'status' => 'string',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(GlAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tillSessions()
    {
        return $this->hasMany(TillSession::class);
    }

    public function cashLedgers()
    {
        return $this->hasMany(CashLedger::class);
    }

    public function balance()
{
    return $this->hasOne(TellerBalance::class);
}
}