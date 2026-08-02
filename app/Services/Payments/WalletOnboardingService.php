<?php

namespace App\Services\Payments;

use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class WalletOnboardingService
{
    /**
     * @throws Exception
     */
    public function onboard(array $data, int $onboardedBy): Wallet
    {
        $tier = (int) ($data['kyc_tier'] ?? 1);
        $bvn = $data['bvn'] ?? null;
        $nin = $data['nin'] ?? null;

        $this->validateKycForTier($tier, $bvn, $nin);

        return DB::transaction(function () use ($data, $onboardedBy, $tier, $bvn, $nin) {

            $wallet = Wallet::create([
                'wallet_no' => $this->generateWalletNo(),
                'owner_name' => $data['owner_name'],
                'phone' => $data['phone'],
                'bvn' => $bvn,
                'nin' => $nin,
                'kyc_tier' => $tier,
                'status' => 'ACTIVE',
                'onboarded_by' => $onboardedBy,
            ]);

            WalletBalance::create([
                'wallet_id' => $wallet->id,
                'currency' => 'NGN',
            ]);

            return $wallet->fresh(['balance']);
        });
    }

    public function suspend(Wallet $wallet): Wallet
    {
        $wallet->update(['status' => 'SUSPENDED']);

        return $wallet->fresh();
    }

    public function reactivate(Wallet $wallet): Wallet
    {
        $wallet->update(['status' => 'ACTIVE']);

        return $wallet->fresh();
    }

    /**
     * Tier 1 requires at least a BVN or NIN. Tier 2 and 3 require both.
     * CONFIRM these requirements with compliance -- see config/wallet.php.
     *
     * @throws Exception
     */
    protected function validateKycForTier(int $tier, ?string $bvn, ?string $nin): void
    {
        if (! in_array($tier, [1, 2, 3], true)) {
            throw new Exception('KYC tier must be 1, 2, or 3.');
        }

        if ($tier === 1 && ! $bvn && ! $nin) {
            throw new Exception('Tier 1 wallets require at least a BVN or NIN.');
        }

        if ($tier >= 2 && (! $bvn || ! $nin)) {
            throw new Exception('Tier 2 and 3 wallets require both a BVN and a NIN.');
        }
    }

    protected function generateWalletNo(): string
    {
        do {
            $walletNo = 'WAL-'.strtoupper(Str::random(8));
        } while (Wallet::where('wallet_no', $walletNo)->exists());

        return $walletNo;
    }
}
