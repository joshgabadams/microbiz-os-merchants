<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\Teller;
use App\Services\Customer\CustomerCashService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class CustomerCashController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerCashService $customerCashService
    ) {
    }

    public function deposit(Request $request)
    {
        try {
            $validated = $request->validate([
                'teller_id' => ['required', 'integer', 'exists:tellers,id'],
                'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
                'amount' => ['required', 'numeric', 'min:1'],
                'performed_by' => ['required', 'integer', 'exists:users,id'],
                'reference' => ['nullable', 'string', 'max:255'],
                'narration' => ['nullable', 'string'],
            ]);

            $teller = Teller::findOrFail($validated['teller_id']);
            $account = CustomerAccount::findOrFail($validated['customer_account_id']);

            $result = $this->customerCashService->deposit(
                $teller,
                $account,
                $validated['amount'],
                $validated['performed_by'],
                $validated['reference'] ?? null,
                $validated['narration'] ?? null
            );

            return $this->success($result, 'Customer deposit processed successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function withdraw(Request $request)
    {
        try {
            $validated = $request->validate([
                'teller_id' => ['required', 'integer', 'exists:tellers,id'],
                'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
                'amount' => ['required', 'numeric', 'min:1'],
                'performed_by' => ['required', 'integer', 'exists:users,id'],
                'reference' => ['nullable', 'string', 'max:255'],
                'narration' => ['nullable', 'string'],
            ]);

            $teller = Teller::findOrFail($validated['teller_id']);
            $account = CustomerAccount::findOrFail($validated['customer_account_id']);

            $result = $this->customerCashService->withdraw(
                $teller,
                $account,
                $validated['amount'],
                $validated['performed_by'],
                $validated['reference'] ?? null,
                $validated['narration'] ?? null
            );

            return $this->success($result, 'Customer withdrawal processed successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}