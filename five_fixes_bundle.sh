#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Services/Vault/VaultService.php << 'MBOS_EOF'
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
MBOS_EOF

cat > app/Services/CashManagement/TransactionReversalService.php << 'MBOS_EOF'
<?php

namespace App\Services\CashManagement;

use App\Events\FinancialTransactionReversed;
use App\Models\CashLedger;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionReversalService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    public function reverseCustomerDeposit(
        CustomerAccountTransaction $customerTransaction,
        TellerTransaction $tellerTransaction,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($customerTransaction->transaction_type !== 'CASH_DEPOSIT') {
            throw new Exception('Only CASH_DEPOSIT transactions can be reversed by this method.');
        }

        if ($tellerTransaction->transaction_type !== 'CUSTOMER_DEPOSIT') {
            throw new Exception('Matching teller transaction must be CUSTOMER_DEPOSIT.');
        }

        return $this->reverseCustomerCashTransaction(
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration ?? 'Reverse customer cash deposit',
            customerBalanceDirection: 'DECREASE',
            tellerBalanceDirection: 'DECREASE',
            ledgerType: 'CUSTOMER_DEPOSIT_REVERSAL',
            ledgerEntryType: 'CREDIT',
            debitAccountKey: 'CUSTOMER_DEPOSIT_CONTROL',
            creditAccountKey: 'TELLER_CASH'
        );
    }

    public function reverseCustomerWithdrawal(
        CustomerAccountTransaction $customerTransaction,
        TellerTransaction $tellerTransaction,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($customerTransaction->transaction_type !== 'CASH_WITHDRAWAL') {
            throw new Exception('Only CASH_WITHDRAWAL transactions can be reversed by this method.');
        }

        if ($tellerTransaction->transaction_type !== 'CUSTOMER_WITHDRAWAL') {
            throw new Exception('Matching teller transaction must be CUSTOMER_WITHDRAWAL.');
        }

        return $this->reverseCustomerCashTransaction(
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration ?? 'Reverse customer cash withdrawal',
            customerBalanceDirection: 'INCREASE',
            tellerBalanceDirection: 'INCREASE',
            ledgerType: 'CUSTOMER_WITHDRAWAL_REVERSAL',
            ledgerEntryType: 'DEBIT',
            debitAccountKey: 'TELLER_CASH',
            creditAccountKey: 'CUSTOMER_WITHDRAWAL_CONTROL'
        );
    }

    protected function reverseCustomerCashTransaction(
        CustomerAccountTransaction $customerTransaction,
        TellerTransaction $tellerTransaction,
        int $performedBy,
        ?string $reference,
        string $narration,
        string $customerBalanceDirection,
        string $tellerBalanceDirection,
        string $ledgerType,
        string $ledgerEntryType,
        string $debitAccountKey,
        string $creditAccountKey
    ): array {
        if ($customerTransaction->is_reversed || $tellerTransaction->is_reversed) {
            throw new Exception('Transaction has already been reversed.');
        }

        return DB::transaction(function () use (
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration,
            $customerBalanceDirection,
            $tellerBalanceDirection,
            $ledgerType,
            $ledgerEntryType,
            $debitAccountKey,
            $creditAccountKey
        ) {
            $amount = (float) $customerTransaction->amount;

            $customerBalance = CustomerAccountBalance::where(
                'customer_account_id',
                $customerTransaction->customer_account_id
            )->lockForUpdate()->first();

            if (!$customerBalance) {
                throw new Exception('Customer balance not found.');
            }

            $tellerBalance = TellerBalance::where(
                'teller_id',
                $tellerTransaction->teller_id
            )->lockForUpdate()->first();

            if (!$tellerBalance) {
                throw new Exception('Teller balance not found.');
            }

            if ($customerBalanceDirection === 'DECREASE' && $customerBalance->available_balance < $amount) {
                throw new Exception('Insufficient customer balance for reversal.');
            }

            if ($tellerBalanceDirection === 'DECREASE' && $tellerBalance->available_balance < $amount) {
                throw new Exception('Insufficient teller cash for reversal.');
            }

            $customerReversal = CustomerAccountTransaction::create([
                'customer_account_id' => $customerTransaction->customer_account_id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'REVERSAL',
                'amount' => $amount,
                'currency' => $customerTransaction->currency,
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
                'reversal_of_transaction_id' => $customerTransaction->id,
            ]);

            $tellerReversal = TellerTransaction::create([
                'teller_id' => $tellerTransaction->teller_id,
                'transaction_no' => $this->transactionNumberService->generate('TLR'),
                'transaction_type' => 'REVERSAL',
                'amount' => $amount,
                'currency' => $tellerTransaction->currency,
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
                'reversal_of_transaction_id' => $tellerTransaction->id,
            ]);

            if ($customerBalanceDirection === 'INCREASE') {
                $customerBalance->ledger_balance += $amount;
                $customerBalance->available_balance += $amount;
            } else {
                $customerBalance->ledger_balance -= $amount;
                $customerBalance->available_balance -= $amount;
            }

            $customerBalance->last_transaction_id = $customerReversal->id;
            $customerBalance->save();

            if ($tellerBalanceDirection === 'INCREASE') {
                $tellerBalance->ledger_balance += $amount;
                $tellerBalance->available_balance += $amount;
            } else {
                $tellerBalance->ledger_balance -= $amount;
                $tellerBalance->available_balance -= $amount;
            }

            $tellerBalance->last_transaction_id = $tellerReversal->id;
            $tellerBalance->save();

            $cashLedger = CashLedger::create([
                'reference_no'       => $tellerReversal->transaction_no,
                'branch_id'          => $tellerTransaction->teller->branch_id,
                'vault_id'           => null,
                'teller_id'          => $tellerTransaction->teller_id,
                'user_id'            => $performedBy,
                'transaction_type'   => $ledgerType,
                'source_type'        => TellerTransaction::class,
                'source_id'          => $tellerReversal->id,
                'entry_type'         => $ledgerEntryType,
                'account_type'       => 'TELLER_CASH',
                'account_code'       => 'TELLER_CASH',
                'debit_account_key'  => $debitAccountKey,
                'credit_account_key' => $creditAccountKey,
                'debit'              => $ledgerEntryType === 'DEBIT' ? $amount : 0,
                'credit'             => $ledgerEntryType === 'CREDIT' ? $amount : 0,
                'running_balance'    => $tellerBalance->ledger_balance,
                'currency'           => $tellerBalance->currency,
                'narration'          => $narration,
                'status'             => 'PENDING',
                'approved_by'        => $performedBy,
                'transaction_date'   => now(),
            ]);

            // Real double-entry GL posting -- both reversal services
            // previously set status=APPROVED directly and never called
            // this, meaning no GlJournal rows were ever created for a
            // reversal, the same class of bug fixed in CustomerCashService
            // earlier this session.
            $this->glPostingService->postFromCashLedger($cashLedger);

            $customerTransaction->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $performedBy,
            ]);

            event(new FinancialTransactionReversed($customerTransaction));

            $tellerTransaction->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $performedBy,
            ]);

            event(new FinancialTransactionReversed($tellerTransaction));

            return [
                'customer_reversal_transaction' => $customerReversal->fresh(),
                'teller_reversal_transaction' => $tellerReversal->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}
MBOS_EOF

rm -f app/Services/Customer/CustomerTransactionReversalService.php

cat > app/Models/User.php << 'MBOS_EOF'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
            'mfa_recovery_codes' => 'array',
        ];
    }

    // ----- RBAC -----

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['assigned_at', 'assigned_by']);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->pluck('name')->intersect($roleNames)->isNotEmpty();
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->contains($permissionName);
    }
}
MBOS_EOF

mkdir -p app/Services/Identity
cat > app/Services/Identity/MfaService.php << 'MBOS_EOF'
<?php

namespace App\Services\Identity;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time password (TOTP) multi-factor authentication.
 *
 * Requires pragmarx/google2fa: composer require pragmarx/google2fa
 * This was not previously a dependency of this project -- confirm it's
 * installed before deploying this code.
 */
class MfaService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'MicroBiz OS'),
            $user->email,
            $secret,
        );
    }

    /**
     * Confirms the user actually has their authenticator app configured
     * correctly (by requiring a valid code) before turning MFA on --
     * enabling MFA with an unconfirmed secret would risk locking the
     * user out immediately.
     */
    public function enable(User $user, string $secret, string $verificationCode): bool
    {
        if (! $this->verifyCode($secret, $verificationCode)) {
            return false;
        }

        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt($secret),
            'mfa_recovery_codes' => $this->generateRecoveryCodes(),
        ]);

        return true;
    }

    public function disable(User $user): void
    {
        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
        ]);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Verifies a code against a user's already-enabled MFA, accepting
     * either a real-time TOTP code or a one-time recovery code.
     */
    public function verifyForUser(User $user, string $code): bool
    {
        if (! $user->mfa_enabled || ! $user->mfa_secret) {
            return false;
        }

        if (in_array($code, $user->mfa_recovery_codes ?? [], true)) {
            $this->consumeRecoveryCode($user, $code);

            return true;
        }

        return $this->verifyCode(decrypt($user->mfa_secret), $code);
    }

    protected function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => strtoupper(bin2hex(random_bytes(5))))
            ->toArray();
    }

    protected function consumeRecoveryCode(User $user, string $code): void
    {
        $remaining = array_values(array_diff($user->mfa_recovery_codes ?? [], [$code]));
        $user->update(['mfa_recovery_codes' => $remaining]);
    }
}
MBOS_EOF

cat > app/Http/Controllers/Api/AuthController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Identity\MfaService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected MfaService $mfaService)
    {
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'mfa_code' => ['nullable', 'string'],
        ]);

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            return $this->error('Invalid credentials.', 401);
        }

        $user = $request->user();

        // Backward compatible: mfa_enabled defaults to false for every
        // existing user, so this only changes behavior for accounts that
        // have explicitly gone through MfaController::enable().
        if ($user->mfa_enabled) {
            if (! ($validated['mfa_code'] ?? null)) {
                return $this->error('MFA code required.', 401);
            }

            if (! $this->mfaService->verifyForUser($user, $validated['mfa_code'])) {
                return $this->error('Invalid MFA code.', 401);
            }
        }

        $token = $user->createToken('microbiz-api')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    public function me(Request $request)
    {
        return $this->success(
            $request->user(),
            'Authenticated user retrieved successfully.'
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }
}
MBOS_EOF

cat > app/Http/Controllers/Api/MfaController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Identity\MfaService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    use ApiResponse;

    public function __construct(protected MfaService $mfaService)
    {
    }

    public function setup(Request $request)
    {
        $secret = $this->mfaService->generateSecret();
        $qrCodeUrl = $this->mfaService->getQrCodeUrl($request->user(), $secret);

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ], 'Scan the QR code with your authenticator app, then confirm with a code via /mfa/enable.');
    }

    public function enable(Request $request)
    {
        $validated = $request->validate([
            'secret' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $enabled = $this->mfaService->enable(
            $request->user(),
            $validated['secret'],
            $validated['code']
        );

        if (! $enabled) {
            return $this->error('Invalid verification code. MFA was not enabled.', 422);
        }

        return $this->success([
            'recovery_codes' => $request->user()->fresh()->mfa_recovery_codes,
        ], 'MFA enabled successfully. Store these recovery codes somewhere safe -- they will not be shown again.');
    }

    public function disable(Request $request)
    {
        $this->mfaService->disable($request->user());

        return $this->success(null, 'MFA disabled successfully.');
    }
}
MBOS_EOF

cat > routes/api.php << 'MBOS_EOF'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfficeSyncController;
use App\Http\Controllers\Api\GlAccountSyncController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\TellerController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\CustomerCashController;
use App\Http\Controllers\Api\BalancingController;
use App\Http\Controllers\Api\BranchEodController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sync/offices', [OfficeSyncController::class, 'sync'])
        ->middleware('permission:offices.sync');
    Route::get('/sync/glaccounts', [GlAccountSyncController::class, 'sync'])
        ->middleware('permission:gl.sync');

    Route::apiResource('vaults', VaultController::class)->only(['index', 'show']);
    Route::apiResource('vaults', VaultController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:vaults.manage');

    Route::apiResource('tellers', TellerController::class)->only(['index', 'show']);
    Route::apiResource('tellers', TellerController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:tellers.manage');

    Route::prefix('v1')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/mfa/setup', [MfaController::class, 'setup']);
        Route::post('/mfa/enable', [MfaController::class, 'enable']);
        Route::post('/mfa/disable', [MfaController::class, 'disable']);

        Route::post('/teller/open', [TellerController::class, 'open'])
            ->middleware('permission:tellers.manage');
        Route::post('/teller/close', [TellerController::class, 'close'])
            ->middleware('permission:tellers.manage');

        Route::post('/float/allocate/request', [ApprovalController::class, 'requestAllocateFloat'])
            ->middleware('permission:approvals.create');
        Route::post('/float/return/request', [ApprovalController::class, 'requestReturnFloat'])
            ->middleware('permission:approvals.create');

        Route::get('/approvals/pending', [ApprovalController::class, 'pending']);
        Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'])
            ->middleware('permission:approvals.approve');
        Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'])
            ->middleware('permission:approvals.reject');

        Route::post('/customer/deposit', [CustomerCashController::class, 'deposit'])
            ->middleware('permission:customer_cash.deposit');
        Route::post('/customer/withdraw', [CustomerCashController::class, 'withdraw'])
            ->middleware('permission:customer_cash.withdraw');

        Route::post('/teller/balance', [BalancingController::class, 'tellerBalance'])
            ->middleware('permission:tellers.manage');
        Route::post('/vault/balance', [BalancingController::class, 'vaultBalance'])
            ->middleware('permission:vaults.manage');

        Route::post('/branch/eod', [BranchEodController::class, 'close'])
            ->middleware('permission:branch_eod.close');

        Route::get('/merchants', [MerchantController::class, 'index']);
        Route::get('/merchants/{merchant}', [MerchantController::class, 'show']);

        Route::post('/merchants/onboard', [MerchantController::class, 'onboard'])
            ->middleware('permission:merchants.onboard');

        Route::post('/merchants/collect/qr', [MerchantController::class, 'collectQr'])
            ->middleware('permission:payments.process');

        Route::post('/merchants/collect/pos', [MerchantController::class, 'collectPos'])
            ->middleware('permission:payments.process');

        Route::post('/merchants/settle', [MerchantController::class, 'settle'])
            ->middleware('permission:merchants.settle');

        Route::get('/wallets', [WalletController::class, 'index']);
        Route::get('/wallets/{wallet}', [WalletController::class, 'show']);

        Route::post('/wallets/onboard', [WalletController::class, 'onboard'])
            ->middleware('permission:wallets.manage');

        Route::post('/wallets/topup', [WalletController::class, 'topUp'])
            ->middleware('permission:wallets.manage');

        Route::post('/wallets/transfer', [WalletController::class, 'transfer'])
            ->middleware('permission:wallets.manage');

        Route::get('/reports/teller-transactions', [ReportController::class, 'tellerTransactions']);
        Route::get('/reports/vault-transactions', [ReportController::class, 'vaultTransactions']);
        Route::get('/reports/teller-ledger', [ReportController::class, 'tellerLedger']);
        Route::get('/reports/vault-ledger', [ReportController::class, 'vaultLedger']);
    });
});
MBOS_EOF

cat > database/migrations/2026_08_03_000001_add_mfa_fields_to_users_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_enabled')->default(false)->after('password');
            $table->text('mfa_secret')->nullable()->after('mfa_enabled');
            $table->json('mfa_recovery_codes')->nullable()->after('mfa_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_enabled', 'mfa_secret', 'mfa_recovery_codes']);
        });
    }
};
MBOS_EOF

cat > concurrency_test.sh << 'MBOS_EOF'
#!/bin/sh
set -e

BASE_URL="http://127.0.0.1:8000"
TELLER_ID=1
CUSTOMER_ACCOUNT_ID=1
TOKEN="PASTE_A_VALID_TOKEN_HERE"
WITHDRAWAL_AMOUNT=1000
CONCURRENT_REQUESTS=10

echo "=== Pre-test balance ==="
curl -s "$BASE_URL/api/tellers/$TELLER_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

echo ""
echo "=== Firing $CONCURRENT_REQUESTS simultaneous withdrawal requests of $WITHDRAWAL_AMOUNT each ==="

for i in $(seq 1 $CONCURRENT_REQUESTS); do
  curl -s -X POST "$BASE_URL/api/v1/customer/withdraw" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"teller_id\":$TELLER_ID,\"customer_account_id\":$CUSTOMER_ACCOUNT_ID,\"amount\":$WITHDRAWAL_AMOUNT}" \
    > "/tmp/concurrency_result_$i.json" &
done

wait

echo ""
echo "=== Results ==="
SUCCESS_COUNT=0
for i in $(seq 1 $CONCURRENT_REQUESTS); do
  RESULT=$(cat "/tmp/concurrency_result_$i.json")
  SUCCESS=$(echo "$RESULT" | python3 -c "import json,sys; print(json.load(sys.stdin).get('success', False))" 2>/dev/null || echo "PARSE_ERROR")
  echo "Request $i: success=$SUCCESS"
  if [ "$SUCCESS" = "True" ]; then
    SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
  fi
done

echo ""
echo "=== Post-test balance ==="
curl -s "$BASE_URL/api/tellers/$TELLER_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

echo ""
echo "=== Verdict ==="
echo "Successful withdrawals: $SUCCESS_COUNT (expected: at most 5, since 5 x 1000 = 5000)"
echo "Manually confirm above that available_balance never went negative."
echo "If more than 5 requests succeeded, or balance is negative, lockForUpdate() did NOT protect against this race condition -- report this back for investigation."

rm -f /tmp/concurrency_result_*.json
MBOS_EOF
chmod +x concurrency_test.sh

echo "All 5 fixes applied. Next: composer require pragmarx/google2fa && php artisan migrate"