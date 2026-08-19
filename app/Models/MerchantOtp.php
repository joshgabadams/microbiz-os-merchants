<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantOtp extends Model
{
    protected $fillable = [
        'challenge_id',
        'fincore_client_id',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
        'verification_token_hash',
        'verification_token_expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'verification_token_expires_at' => 'datetime',
    ];
}
