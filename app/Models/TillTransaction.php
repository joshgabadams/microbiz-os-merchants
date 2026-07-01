<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TillTransaction extends Model
{
    protected $fillable = [
        'till_session_id',
        'teller_id',
        'branch_id',
        'vault_id',
        'type',
        'direction',
        'amount',
        'reference',
        'narration',
        'status',
        'posted_at',
        'reversed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(TillSession::class, 'till_session_id');
    }

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
}