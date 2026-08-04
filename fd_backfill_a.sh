#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os
mkdir -p app/Http/Requests/FixedDeposit

cat > database/migrations/2026_08_04_000002_create_fixed_deposits_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposits', function (Blueprint $table) {

            $table->id();

            $table->string('fd_no')->unique();

            $table->foreignId('customer_account_id')
                ->constrained('customer_accounts')
                ->restrictOnDelete();

            $table->foreignId('settlement_account_id')
                ->constrained('customer_accounts')
                ->restrictOnDelete();

            $table->decimal('principal_amount', 24, 2);
            $table->string('currency', 3)->default('NGN');

            $table->decimal('interest_rate', 8, 4);

            $table->decimal('pre_liquidation_rate', 8, 4);

            $table->decimal('pre_liquidation_penalty_fee', 24, 2)->default(0);

            $table->unsignedInteger('tenor_days');
            $table->date('start_date');
            $table->date('maturity_date');

            $table->enum('status', [
                'ACTIVE',
                'LIQUIDATED_EARLY',
                'LIQUIDATED_AT_MATURITY',
            ])->default('ACTIVE');

            $table->foreignId('booked_by')->constrained('users');

            $table->foreignId('liquidated_by')->nullable()->constrained('users');
            $table->timestamp('liquidated_at')->nullable();
            $table->decimal('interest_paid', 24, 2)->nullable();

            $table->text('narration')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits');
    }
};
MBOS_EOF

cat > app/Models/FixedDeposit.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDeposit extends Model
{
    protected $fillable = [
        'fd_no',
        'customer_account_id',
        'settlement_account_id',
        'principal_amount',
        'currency',
        'interest_rate',
        'pre_liquidation_rate',
        'pre_liquidation_penalty_fee',
        'tenor_days',
        'start_date',
        'maturity_date',
        'status',
        'booked_by',
        'liquidated_by',
        'liquidated_at',
        'interest_paid',
        'narration',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'pre_liquidation_rate' => 'decimal:4',
        'pre_liquidation_penalty_fee' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'liquidated_at' => 'datetime',
    ];

    public function sourceAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function settlementAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'settlement_account_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function liquidatedBy()
    {
        return $this->belongsTo(User::class, 'liquidated_by');
    }
}
MBOS_EOF

cat > app/Http/Requests/FixedDeposit/BookFixedDepositRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\FixedDeposit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BookFixedDepositRequest extends FormRequest
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
            'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'settlement_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pre_liquidation_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pre_liquidation_penalty_fee' => ['nullable', 'numeric', 'min:0'],
            'tenor_days' => ['required', 'integer', 'min:1'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
MBOS_EOF

cat > app/Http/Requests/FixedDeposit/LiquidateFixedDepositRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\FixedDeposit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LiquidateFixedDepositRequest extends FormRequest
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
            'narration' => ['nullable', 'string'],
        ];
    }
}
MBOS_EOF

cat > app/Http/Controllers/Api/FixedDepositController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedDeposit\BookFixedDepositRequest;
use App\Http\Requests\FixedDeposit\LiquidateFixedDepositRequest;
use App\Models\CustomerAccount;
use App\Models\FixedDeposit;
use App\Services\Deposits\FixedDepositService;
use App\Traits\ApiResponse;
use Exception;

class FixedDepositController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FixedDepositService $fixedDepositService
    ) {
    }

    public function index()
    {
        return $this->success(
            FixedDeposit::with(['sourceAccount', 'settlementAccount'])->latest()->get(),
            'Fixed deposits retrieved successfully.'
        );
    }

    public function show(FixedDeposit $fixedDeposit)
    {
        return $this->success(
            $fixedDeposit->load(['sourceAccount', 'settlementAccount']),
            'Fixed deposit retrieved successfully.'
        );
    }

    public function book(BookFixedDepositRequest $request)
    {
        try {
            $sourceAccount = CustomerAccount::findOrFail($request->customer_account_id);
            $settlementAccount = CustomerAccount::findOrFail($request->settlement_account_id);

            $fixedDeposit = $this->fixedDepositService->book(
                $sourceAccount,
                $settlementAccount,
                (float) $request->principal_amount,
                (float) $request->interest_rate,
                (float) $request->pre_liquidation_rate,
                (float) ($request->pre_liquidation_penalty_fee ?? 0),
                (int) $request->tenor_days,
                $request->user()->id,
                $request->narration
            );

            return $this->success($fixedDeposit, 'Fixed deposit booked successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function previewLiquidation(FixedDeposit $fixedDeposit)
    {
        try {
            $preview = $this->fixedDepositService->previewLiquidation($fixedDeposit);

            return $this->success($preview, 'Liquidation preview calculated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function liquidate(LiquidateFixedDepositRequest $request, FixedDeposit $fixedDeposit)
    {
        try {
            $result = $this->fixedDepositService->liquidate(
                $fixedDeposit,
                $request->user()->id,
                $request->narration
            );

            return $this->success($result, 'Fixed deposit liquidated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
MBOS_EOF

echo "FD Backfill Part A applied (migration, model, requests, controller)."