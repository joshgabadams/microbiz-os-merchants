#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os
mkdir -p app/Http/Requests/CashManagement

cat > database/migrations/2026_08_09_000001_add_customer_account_transaction_id_to_teller_transactions.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teller_transactions', function (Blueprint $table) {
            $table->foreignId('customer_account_transaction_id')
                ->nullable()
                ->after('teller_id')
                ->constrained('customer_account_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teller_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_account_transaction_id');
        });
    }
};
MBOS_EOF

cat > app/Models/TellerTransaction.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TellerTransaction extends Model
{
    protected $fillable = [

        'teller_id',

        'customer_account_transaction_id',

        'transaction_no',

        'transaction_type',

        'amount',

        'currency',

        'reference',

        'narration',

        'performed_by',

        'approved_by',

        'transaction_date',

        'posted',

        'reversal_of_transaction_id',
        'is_reversed',
        'reversed_at',
         'reversed_by',

    ];

    protected $casts = [

        'amount' => 'decimal:2',

        'transaction_date' => 'datetime',

        'posted' => 'boolean',

        'is_reversed' => 'boolean',
'reversed_at' => 'datetime',

    ];

    public function teller()
    {
        return $this->belongsTo(Teller::class);
    }

    public function customerAccountTransaction()
    {
        return $this->belongsTo(CustomerAccountTransaction::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
MBOS_EOF

cat > app/Services/Customer/CustomerCashService.php << 'MBOS_EOF'
<?php

namespace App\Services\Customer;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Services\Accounting\GlPostingService;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerCashService
{
    public function __construct(
        protected CustomerAccountService $customerAccountService,
        protected GlPostingService $glPostingService
    ) {
    }

    public function deposit(
        Teller $teller,
        CustomerAccount $account,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $teller,
            $account,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            if (!$teller->active) {
                throw new Exception('Teller is inactive.');
            }

            if ($teller->status !== 'OPEN') {
                throw new Exception('Teller must be open to process customer deposits.');
            }

            if ($account->status !== 'ACTIVE') {
                throw new Exception('Customer account is not active.');
            }

            $tellerBalance = TellerBalance::where('teller_id', $teller->id)
                ->lockForUpdate()
                ->first();

            if (!$tellerBalance) {
                throw new Exception('Teller balance not found.');
            }

            $customerTransaction = $this->customerAccountService->deposit(
                $account,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Customer cash deposit'
            );

            $tellerTransaction = TellerTransaction::create([
                'teller_id' => $teller->id,
                'customer_account_transaction_id' => $customerTransaction->id,
                'transaction_no' => app(\App\Services\Common\TransactionNumberService::class)->generate('TLR'),
                'transaction_type' => 'CUSTOMER_DEPOSIT',
                'amount' => $amount,
                'currency' => $tellerBalance->currency,
                'reference' => $reference,
                'narration' => $narration ?? 'Customer cash deposit',
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ]);

            event(new FinancialTransactionCreated($tellerTransaction));

            $tellerBalance->ledger_balance += $amount;
            $tellerBalance->available_balance += $amount;
            $tellerBalance->last_transaction_id = $tellerTransaction->id;
            $tellerBalance->save();

            $cashLedger = CashLedger::create([
                'reference_no'       => $tellerTransaction->transaction_no,
                'branch_id'          => $teller->branch_id,
                'vault_id'           => $teller->vault_id,
                'teller_id'          => $teller->id,
                'user_id'            => $performedBy,
                'transaction_type'   => 'CUSTOMER_CASH_DEPOSIT',
                'source_type'        => TellerTransaction::class,
                'source_id'          => $tellerTransaction->id,
                'entry_type'         => 'DEBIT',
                'account_type'       => 'TELLER_CASH',
                'account_code'       => 'TELLER_CASH',
                'debit_account_key'  => 'TELLER_CASH',
                'credit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
                'debit'              => $amount,
                'credit'             => 0,
                'running_balance'    => $tellerBalance->ledger_balance,
                'currency'           => $tellerBalance->currency,
                'narration'          => $narration ?? 'Customer cash deposit',
                'status'             => 'PENDING',
                'approved_by'        => null,
                'transaction_date'   => now(),
            ]);

            $tellerTransaction->update([
    'posted' => true,
]);

$cashLedger->update([
    'approved_by' => $performedBy,
]);

$this->glPostingService->postFromCashLedger($cashLedger);

            return [
                'customer_transaction' => $customerTransaction,
                'teller_transaction' => $tellerTransaction->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }

    public function withdraw(
    Teller $teller,
    CustomerAccount $account,
    float $amount,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): array {
    if ($amount <= 0) {
        throw new Exception('Withdrawal amount must be greater than zero.');
    }

    return DB::transaction(function () use (
        $teller,
        $account,
        $amount,
        $performedBy,
        $reference,
        $narration
    ) {
        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        if ($teller->status !== 'OPEN') {
            throw new Exception('Teller must be open to process customer withdrawals.');
        }

        if ($account->status !== 'ACTIVE') {
            throw new Exception('Customer account is not active.');
        }

        $tellerBalance = TellerBalance::where('teller_id', $teller->id)
            ->lockForUpdate()
            ->first();

        if (!$tellerBalance) {
            throw new Exception('Teller balance not found.');
        }

        if ($tellerBalance->available_balance < $amount) {
            throw new Exception('Insufficient teller cash balance.');
        }

        $customerTransaction = $this->customerAccountService->withdraw(
            $account,
            $amount,
            $performedBy,
            $reference,
            $narration ?? 'Customer cash withdrawal'
        );

        $tellerTransaction = TellerTransaction::create([
            'teller_id' => $teller->id,
            'customer_account_transaction_id' => $customerTransaction->id,
            'transaction_no' => app(\App\Services\Common\TransactionNumberService::class)->generate('TLR'),
            'transaction_type' => 'CUSTOMER_WITHDRAWAL',
            'amount' => $amount,
            'currency' => $tellerBalance->currency,
            'reference' => $reference,
            'narration' => $narration ?? 'Customer cash withdrawal',
            'performed_by' => $performedBy,
            'approved_by' => null,
            'transaction_date' => now(),
            'posted' => false,
        ]);

        event(new FinancialTransactionCreated($tellerTransaction));

        $tellerBalance->ledger_balance -= $amount;
        $tellerBalance->available_balance -= $amount;
        $tellerBalance->last_transaction_id = $tellerTransaction->id;
        $tellerBalance->save();

        $cashLedger = CashLedger::create([
            'reference_no'       => $tellerTransaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'CUSTOMER_CASH_WITHDRAWAL',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $tellerTransaction->id,
            'entry_type'         => 'CREDIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'CUSTOMER_WITHDRAWAL_CONTROL',
            'credit_account_key' => 'TELLER_CASH',
            'debit'              => 0,
            'credit'             => $amount,
            'running_balance'    => $tellerBalance->ledger_balance,
            'currency'           => $tellerBalance->currency,
            'narration'          => $narration ?? 'Customer cash withdrawal',
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => now(),
        ]);

        $tellerTransaction->update([
            'posted' => true,
        ]);

        $cashLedger->update([
            'approved_by' => $performedBy,
        ]);

        $this->glPostingService->postFromCashLedger($cashLedger);

        return [
            'customer_transaction' => $customerTransaction,
            'teller_transaction' => $tellerTransaction->fresh(),
            'cash_ledger' => $cashLedger->fresh(),
        ];
    });
}

}
MBOS_EOF

cat > app/Http/Requests/CashManagement/ReverseCustomerTransactionRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\CashManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReverseCustomerTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
MBOS_EOF

cat > app/Http/Controllers/Api/TransactionReversalController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashManagement\ReverseCustomerTransactionRequest;
use App\Models\CustomerAccountTransaction;
use App\Services\CashManagement\TransactionReversalService;
use App\Traits\ApiResponse;
use Exception;

class TransactionReversalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionReversalService $reversalService
    ) {
    }

    public function reverse(ReverseCustomerTransactionRequest $request, CustomerAccountTransaction $customerAccountTransaction)
    {
        try {
            $tellerTransaction = \App\Models\TellerTransaction::where(
                'customer_account_transaction_id',
                $customerAccountTransaction->id
            )->firstOrFail();

            $result = match ($customerAccountTransaction->transaction_type) {
                'CASH_DEPOSIT' => $this->reversalService->reverseCustomerDeposit(
                    $customerAccountTransaction,
                    $tellerTransaction,
                    $request->user()->id,
                    $request->reference,
                    $request->narration
                ),
                'CASH_WITHDRAWAL' => $this->reversalService->reverseCustomerWithdrawal(
                    $customerAccountTransaction,
                    $tellerTransaction,
                    $request->user()->id,
                    $request->reference,
                    $request->narration
                ),
                default => throw new Exception(
                    "Transaction type {$customerAccountTransaction->transaction_type} cannot be reversed via this endpoint."
                ),
            };

            return $this->success($result, 'Transaction reversed successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
MBOS_EOF

echo "Reversal Part 1 of 2 applied (migration, model, service, request, controller)."