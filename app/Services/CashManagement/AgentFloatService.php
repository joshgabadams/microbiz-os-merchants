<?php

namespace App\Services\CashManagement;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\CashLedger;
use App\Models\Vault;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use App\Services\Payments\AgentOperationGuard;
use App\Services\Vault\VaultTransactionService;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentFloatService
{
    public function __construct(
        protected VaultTransactionService $vaultTransactionService,
        protected AgentOperationGuard $guard,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function allocateFloat(
        Vault $vault,
        Agent $agent,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Float amount must be greater than zero.');
        }

        $this->guard->guardFloatOperation($agent, $amount);

        return DB::transaction(function () use (
            $vault,
            $agent,
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
                $narration ?? 'Vault to Agent Float'
            );

            $this->ensureBalanceExists($agent);

            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            $balance->ledger_float += $amount;
            $balance->available_float += $amount;
            $balance->save();

            $transactionDate = now();

            $cashLedger = CashLedger::create([
                'reference_no' => $this->transactionNumberService->generate('AGF'),
                'branch_id' => $agent->branch_id,
                'vault_id' => $vault->id,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_FLOAT_RECEIPT',
                'source_type' => Agent::class,
                'source_id' => $agent->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'AGENCY_FLOAT',
                'credit_account_key' => 'VAULT_CASH',
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Vault to Agent Float',
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => $transactionDate,
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $balance->last_transaction_id = $cashLedger->id;
            $balance->save();

            return [
                'vault_transaction' => $vaultTransaction,
                'agent_balance' => $balance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }

    /**
     * @throws Exception
     */
    public function returnFloat(
        Vault $vault,
        Agent $agent,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Float amount must be greater than zero.');
        }

        $this->guard->guardFloatOperation($agent, $amount);

        return DB::transaction(function () use (
            $vault,
            $agent,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new Exception('Agent has no float balance to return.');
            }

            if ($balance->available_float < $amount) {
                throw new Exception('Insufficient agent float balance.');
            }

            $balance->ledger_float -= $amount;
            $balance->available_float -= $amount;
            $balance->save();

            $transactionDate = now();

            $cashLedger = CashLedger::create([
                'reference_no' => $this->transactionNumberService->generate('AGF'),
                'branch_id' => $agent->branch_id,
                'vault_id' => $vault->id,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_FLOAT_RETURN',
                'source_type' => Agent::class,
                'source_id' => $agent->id,
                'entry_type' => 'CREDIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'VAULT_CASH',
                'credit_account_key' => 'AGENCY_FLOAT',
                'debit' => 0,
                'credit' => $amount,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Agent return float to vault',
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => $transactionDate,
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $balance->last_transaction_id = $cashLedger->id;
            $balance->save();

            $vaultTransaction = $this->vaultTransactionService->deposit(
                $vault,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Agent return float to vault'
            );

            return [
                'agent_balance' => $balance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
                'vault_transaction' => $vaultTransaction,
            ];
        });
    }

    protected function ensureBalanceExists(Agent $agent): void
    {
        AgentBalance::firstOrCreate(
            ['agent_id' => $agent->id],
            ['currency' => 'NGN']
        );
    }
}
