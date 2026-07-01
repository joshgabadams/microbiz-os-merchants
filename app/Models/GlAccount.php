<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlAccount extends Model
{
    protected $fillable = [
        'account_code',
        'account_name',
        'account_type', // ASSET, LIABILITY, INCOME, EXPENSE
        'parent_id',
        'normal_balance', // DEBIT or CREDIT
        'is_postable',
        'currency'
    ];

    public function parent()
    {
        return $this->belongsTo(GlAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(GlAccount::class, 'parent_id');
    }
}