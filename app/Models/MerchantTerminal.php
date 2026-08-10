<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTerminal extends Model
{
    protected $fillable = [
        'merchant_id',
        'merchant_location_id',
        'terminal_id',
        'serial_number',
        'terminal_type',
        'provider',
        'model',
        'status',
        'application_version',
        'activated_at',
        'last_heartbeat_at',
        'last_transaction_at',
        'assigned_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_transaction_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function location()
    {
        return $this->belongsTo(MerchantLocation::class, 'merchant_location_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
