#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

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
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'backdate_reason' => ['nullable', 'string', 'max:1000'],
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
use Carbon\Carbon;
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
            if ($request->filled('start_date') && Carbon::parse($request->start_date)->lt(Carbon::today())) {
                if (! $request->user()->hasPermission('fixed_deposits.backdate')) {
                    return $this->error('Missing required permission: fixed_deposits.backdate.', 403);
                }
            }

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
                (int) $request->branch_id,
                $request->user()->id,
                $request->narration,
                $request->filled('start_date') ? Carbon::parse($request->start_date) : null,
                $request->backdate_reason
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
                (int) $request->branch_id,
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

cat > database/seeders/RbacSeeder.php << 'MBOS_EOF'
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'vaults.manage' => 'vault',
            'tellers.manage' => 'teller',
            'approvals.create' => 'approval',
            'approvals.approve' => 'approval',
            'approvals.reject' => 'approval',
            'customer_cash.deposit' => 'customer-cash',
            'customer_cash.withdraw' => 'customer-cash',
            'branch_eod.close' => 'branch',
            'gl.sync' => 'gl',
            'offices.sync' => 'sync',
            'fixed_deposits.book' => 'fixed-deposit',
            'fixed_deposits.liquidate' => 'fixed-deposit',
            'fixed_deposits.backdate' => 'fixed-deposit',
        ];

        foreach ($permissions as $name => $module) {
            Permission::firstOrCreate(['name' => $name], ['module' => $module]);
        }

        $roles = [
            'admin' => [
                'label' => 'Platform Administrator',
                'is_system' => true,
                'permissions' => array_keys($permissions),
            ],
            'teller-officer' => [
                'label' => 'Teller Officer',
                'permissions' => ['tellers.manage', 'customer_cash.deposit', 'customer_cash.withdraw'],
            ],
            'deposit-officer' => [
                'label' => 'Deposit Officer',
                'permissions' => ['fixed_deposits.book', 'fixed_deposits.liquidate'],
            ],
            'deposit-supervisor' => [
                'label' => 'Deposit Supervisor',
                'permissions' => ['fixed_deposits.book', 'fixed_deposits.liquidate', 'fixed_deposits.backdate'],
            ],
            'vault-officer' => [
                'label' => 'Vault Officer',
                'permissions' => ['vaults.manage', 'approvals.create'],
            ],
            'branch-manager' => [
                'label' => 'Branch Manager',
                'permissions' => ['approvals.approve', 'approvals.reject', 'branch_eod.close'],
            ],
            'compliance-officer' => [
                'label' => 'Compliance Officer',
                'permissions' => ['gl.sync', 'offices.sync'],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $config['label'], 'is_system' => $config['is_system'] ?? false],
            );

            $permissionIds = Permission::whereIn('name', $config['permissions'])->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $firstUser = User::first();

        if ($firstUser) {
            $adminRole = Role::where('name', 'admin')->first();
            $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);

            $this->command->info("Attached 'admin' role to existing user: {$firstUser->email}");
        } else {
            $this->command->warn('No existing user found -- run this after your user seeder, or create a user and re-run.');
        }
    }
}
MBOS_EOF

cat > tests/Feature/FixedDepositBackdatingTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\FixedDeposit;
use App\Models\User;
use App\Services\Deposits\FixedDepositService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class FixedDepositBackdatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);
    }

    protected function makeFundedAccount(float $balance): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $account = CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'status' => 'ACTIVE',
        ]);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $account;
    }

    public function test_backdating_within_window_with_reason_succeeds(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            'Correcting a delayed paper-form entry.'
        );

        $this->assertEquals(Carbon::today()->subDays(10)->toDateString(), $fd->start_date->toDateString());
        $this->assertEquals('Correcting a delayed paper-form entry.', $fd->backdate_reason);
        $this->assertEquals(Carbon::today()->subDays(10)->addDays(365)->toDateString(), $fd->maturity_date->toDateString());
    }

    public function test_backdating_without_reason_is_rejected(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('backdate reason is required');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            null
        );
    }

    public function test_backdating_beyond_max_window_is_rejected(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('more than 30 days');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(31),
            'Trying to backdate too far.'
        );
    }

    public function test_future_start_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be in the future');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::tomorrow(),
            'Should never get this far.'
        );
    }

    public function test_non_backdated_booking_does_not_require_a_reason(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id
        );

        $this->assertNull($fd->backdate_reason);
        $this->assertEquals(Carbon::today()->toDateString(), $fd->start_date->toDateString());
    }

    public function test_backdated_fd_cannot_be_liquidated_before_minimum_holding_period(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            'Backdated for testing the holding period.'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be liquidated for another');

        app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);
    }

    public function test_backdated_fd_can_be_liquidated_after_minimum_holding_period(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            'Backdated for testing the holding period.'
        );

        FixedDeposit::where('id', $fd->id)->update([
            'created_at' => now()->subHours(25),
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd->fresh(), $branch->id, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
        $this->assertGreaterThan(0, (float) $result['calculation']['net_interest']);
    }

    public function test_non_backdated_fd_has_no_holding_period_restriction(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id
        );

        $result = app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
    }
}
MBOS_EOF

echo "Backdating Part 2 of 3 applied (request, controller, RBAC, tests). Next: php artisan migrate && php artisan db:seed --class=\"Database\Seeders\RbacSeeder\" && php artisan test --filter=FixedDepositBackdatingTest"