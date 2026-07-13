<?php

namespace App\Services\CashManagement;

use App\Models\Vault;
use App\Models\Teller;
use App\Services\Vault\VaultTransactionService;
use App\Services\Teller\TellerTransactionService;
use Illuminate\Support\Facades\DB;
use Exception;

class VaultTellerFloatService
{
    public function __construct(
        protected VaultTransactionService $vaultTransactionService,
        protected TellerTransactionService $tellerTransactionService
    ) {
    }

    /**
     * Allocate float from a vault to a teller.
     *
     * @throws Exception
     */
    public function allocateFloat(
        Vault $vault,
        Teller $teller,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Float amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $vault,
            $teller,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            $vaultTransaction = $this->vaultTransactionService->withdraw(
                $vault,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Vault to Teller Float'
            );

            $tellerTransaction = $this->tellerTransactionService->receiveFloat(
                $teller,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Vault to Teller Float'
            );

            return [
                'vault_transaction' => $vaultTransaction,
                'teller_transaction' => $tellerTransaction,
            ];
        });
    }

    /**
     * Return float from a teller back to a vault.
     *
     * @throws Exception
     */
    public function returnFloat(
        Vault $vault,
        Teller $teller,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Float amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $vault,
            $teller,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            $tellerTransaction = $this->tellerTransactionService->returnFloat(
                $teller,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Teller return float to vault'
            );

            $vaultTransaction = $this->vaultTransactionService->deposit(
                $vault,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Teller return float to vault'
            );

            return [
                'teller_transaction' => $tellerTransaction,
                'vault_transaction' => $vaultTransaction,
            ];
        });
    }
}