<?php

namespace App\Services\Teller;

use App\Models\Teller;

class TellerService
{
    public function create(array $data): Teller
    {
        return Teller::create($data);
    }

    public function all()
{
    return Teller::with(['branch', 'vault', 'glAccount', 'balance'])->latest()->get();
}
}