<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'office_id'
    ];

    public function vaults()
    {
        return $this->hasMany(Vault::class);
    }

    public function tellers()
    {
        return $this->hasMany(Teller::class);
    }
}