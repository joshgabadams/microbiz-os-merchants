<?php
namespace App\Services\Vault;

use App\Models\Vault;

class VaultService
{
    public function all()
    {
        return Vault::with([
            'branch',
            'glAccount'
        ])->get();
    }

    public function create(array $data)
    {
        return Vault::create($data);
    }

    public function update(Vault $vault, array $data)
    {
        $vault->update($data);

        return $vault;
    }

    public function delete(Vault $vault)
    {
        return $vault->delete();
    }
}