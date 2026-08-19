<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantIdentity extends Model
{
    protected $fillable = [
        'fincore_client_id',
        'idempotency_key',
        'fincore_account_no',
        'legal_form',
        'display_name',
        'phone',
        'email',
        'status',
    ];
}
