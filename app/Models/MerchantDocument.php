<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantDocument extends Model
{
    protected $fillable = [
        'merchant_id',
        'document_type',
        'document_number',
        'storage_path',
        'issued_at',
        'expires_at',
        'verification_status',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
