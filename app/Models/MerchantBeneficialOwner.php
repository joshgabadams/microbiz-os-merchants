<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantBeneficialOwner extends Model
{
    protected $fillable = [
        'merchant_id',
        'full_name',
        'date_of_birth',
        'nationality',
        'identification_type',
        'identification_number',
        'ownership_percentage',
        'is_director',
        'is_pep',
        'sanctions_match',
        'screening_status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_director' => 'boolean',
        'is_pep' => 'boolean',
        'sanctions_match' => 'boolean',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
