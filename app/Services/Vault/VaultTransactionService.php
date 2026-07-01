<?php

namespace App\Services\Vault;

use App\Models\Vault;
use App\Models\VaultBalance;
use App\Models\VaultTransaction;
use App\Services\Common\TransactionNumberService;
use App\Services\Accounting\GlPostingService;
use Illuminate\Support\Facades\DB;
use Exception;

class VaultTransactionService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * Deposit cash into a vault.
     *
     * @throws Exception
     */
    public function deposit(
        Vault $vault,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): VaultTransaction {

        // Validate deposit amount
        if ($amount <= 0) {
            throw new Exception('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $vault,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {

            /*
             |--------------------------------------------------------------
             | Lock Vault Balance
             |--------------------------------------------------------------
             | Prevent concurrent updates to the same vault.
             */
            $balance = VaultBalance::where('vault_id', $vault->id)
                ->lockForUpdate()
                ->first();

            /*
             |--------------------------------------------------------------
             | Create Initial Balance Record
             |--------------------------------------------------------------
             */
            if (!$balance) {

                $balance = VaultBalance::create([
                    'vault_id'          => $vault->id,
                    'currency'          => $vault->currency ?? 'NGN',
                    'ledger_balance'    => 0,
                    'available_balance' => 0,
                    'locked_balance'    => 0,
                ]);

                $balance->refresh();
            }

            /*
             |--------------------------------------------------------------
             | Generate Transaction Number
             |--------------------------------------------------------------
             */
            $transactionNumber = $this->transactionNumberService
                ->generate('VLT');

            /*
             |--------------------------------------------------------------
             | Create Vault Transaction
             |--------------------------------------------------------------
             */
            $transaction = VaultTransaction::create([

                'vault_id'          => $vault->id,

                'transaction_no'    => $transactionNumber,

                'transaction_type'  => 'DEPOSIT',

                'amount'            => $amount,

                'currency'          => $balance->currency,

                'reference'         => $reference,

                'narration'         => $narration,

                'performed_by'      => $performedBy,

                'approved_by'       => null,

                'transaction_date'  => now(),

                'posted'            => false,

            ]);

            /*
             |--------------------------------------------------------------
             | Stage B ends here.
             |
             | Stage C:
             |   Update Vault Balance
             |
             | Stage D:
             |   Create Cash Ledger
             |
             | Stage E:
             |   Post GL Entries
             |
             | Stage F:
             |   Audit Logging
             |--------------------------------------------------------------
             */

            return $transaction;
        });
    }
}