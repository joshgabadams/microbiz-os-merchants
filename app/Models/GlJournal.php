<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlJournal extends Model
{
    protected $fillable = [
        'gl_account_id',
        'entry_type', // DEBIT or CREDIT
        'amount',
        'reference',
        'source_type',
        'source_id',
        'posted_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(GlAccount::class);
    }
}
