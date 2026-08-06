<?php
namespace App\Services\Vault;

use App\Models\Vault;
use Illuminate\Support\Str;

class VaultService
{
    public function all()
    {
        return Vault::with([
            'branch',
            'glAccount',
            'balance'
        ])->get();
    }

    public function create(array $data)
    {
        // 'code' is a required, unique column that StoreVaultRequest never
        // validates and nothing else supplied -- meaning vault creation
        // failed with a database error until this fix. Auto-generate it,
        // matching the MCH-/WAL- pattern already used for Merchant/Wallet.
        $data['code'] = $data['code'] ?? $this->generateVaultCode();

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

    protected function generateVaultCode(): string
    {
        do {
            $code = 'VLT-'.strtoupper(Str::random(8));
        } while (Vault::where('code', $code)->exists());

        return $code;
    }
}
