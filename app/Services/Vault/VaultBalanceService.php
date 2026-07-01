<?php

namespace App\Services\Vault;

use App\Models\Vault;
use App\Models\VaultBalance;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VaultBalanceService
{
    /**
     * Retrieve the vault balance.
     * Creates it automatically if it does not exist.
     *
     * @param bool $lock Acquire a database row lock.
     */
    public function getBalance(Vault $vault, bool $lock = false): VaultBalance
    {
        VaultBalance::firstOrCreate(
            [
                'vault_id' => $vault->id,
                'currency' => $vault->currency,
            ],
            [
                'ledger_balance'    => 0,
                'available_balance' => 0,
                'locked_balance'    => 0,
            ]
        );

        $query = VaultBalance::where('vault_id', $vault->id)
            ->where('currency', $vault->currency);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /**
     * Increase vault balance.
     */
    public function increase(Vault $vault, float $amount): VaultBalance
    {
        return DB::transaction(function () use ($vault, $amount) {

            $balance = $this->getBalance($vault, true);

            $balance->ledger_balance += $amount;
            $balance->available_balance += $amount;
            $balance->last_transaction_at = now();

            $this->validateBalance($balance);

            $balance->save();

            return $balance->fresh();
        });
    }

    /**
     * Decrease vault balance.
     */
    public function decrease(Vault $vault, float $amount): VaultBalance
    {
        return DB::transaction(function () use ($vault, $amount) {

            $balance = $this->getBalance($vault, true);

            if ($balance->available_balance < $amount) {
                throw new RuntimeException('Insufficient available vault balance.');
            }

            $balance->ledger_balance -= $amount;
            $balance->available_balance -= $amount;
            $balance->last_transaction_at = now();

            $this->validateBalance($balance);

            $balance->save();

            return $balance->fresh();
        });
    }

    /**
     * Reserve funds.
     */
    public function lockFunds(Vault $vault, float $amount): VaultBalance
    {
        return DB::transaction(function () use ($vault, $amount) {

            $balance = $this->getBalance($vault, true);

            if ($balance->available_balance < $amount) {
                throw new RuntimeException('Insufficient available balance.');
            }

            $balance->available_balance -= $amount;
            $balance->locked_balance += $amount;
            $balance->last_transaction_at = now();

            $this->validateBalance($balance);

            $balance->save();

            return $balance->fresh();
        });
    }

    /**
     * Release reserved funds.
     */
    public function releaseFunds(Vault $vault, float $amount): VaultBalance
    {
        return DB::transaction(function () use ($vault, $amount) {

            $balance = $this->getBalance($vault, true);

            if ($balance->locked_balance < $amount) {
                throw new RuntimeException('Locked balance is insufficient.');
            }

            $balance->locked_balance -= $amount;
            $balance->available_balance += $amount;
            $balance->last_transaction_at = now();

            $this->validateBalance($balance);

            $balance->save();

            return $balance->fresh();
        });
    }

    /**
     * Available balance.
     */
    public function available(Vault $vault): float
    {
        return (float) $this->getBalance($vault)->available_balance;
    }

    /**
     * Ledger balance.
     */
    public function ledger(Vault $vault): float
    {
        return (float) $this->getBalance($vault)->ledger_balance;
    }

    /**
     * Locked balance.
     */
    public function locked(Vault $vault): float
    {
        return (float) $this->getBalance($vault)->locked_balance;
    }

    /**
     * Check available funds.
     */
    public function hasAvailableFunds(Vault $vault, float $amount): bool
    {
        return $this->available($vault) >= $amount;
    }

    /**
     * Ensure balance integrity.
     *
     * Ledger = Available + Locked
     */
    protected function validateBalance(VaultBalance $balance): void
    {
        $expected = $balance->available_balance + $balance->locked_balance;

        if (round($balance->ledger_balance, 2) !== round($expected, 2)) {
            throw new RuntimeException(
                'Vault balance integrity violation.'
            );
        }
    }
}