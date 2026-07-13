<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teller;
use App\Models\Vault;
use App\Services\Teller\TellerBalancingService;
use App\Services\Vault\VaultBalancingService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class BalancingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TellerBalancingService $tellerBalancingService,
        protected VaultBalancingService $vaultBalancingService
    ) {
    }

    public function tellerBalance(Request $request)
    {
        try {
            $validated = $request->validate([
                'teller_id' => ['required', 'integer', 'exists:tellers,id'],
                'business_date' => ['required', 'date'],
                'physical_cash' => ['required', 'numeric', 'min:0'],
                'balanced_by' => ['required', 'integer', 'exists:users,id'],
                'note' => ['nullable', 'string'],
            ]);

            $teller = Teller::findOrFail($validated['teller_id']);

            $result = $this->tellerBalancingService->balance(
                $teller,
                $validated['business_date'],
                $validated['physical_cash'],
                $validated['balanced_by'],
                $validated['note'] ?? null
            );

            return $this->success($result, 'Teller balanced successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function vaultBalance(Request $request)
    {
        try {
            $validated = $request->validate([
                'vault_id' => ['required', 'integer', 'exists:vaults,id'],
                'business_date' => ['required', 'date'],
                'physical_cash' => ['required', 'numeric', 'min:0'],
                'balanced_by' => ['required', 'integer', 'exists:users,id'],
                'note' => ['nullable', 'string'],
            ]);

            $vault = Vault::findOrFail($validated['vault_id']);

            $result = $this->vaultBalancingService->balance(
                $vault,
                $validated['business_date'],
                $validated['physical_cash'],
                $validated['balanced_by'],
                $validated['note'] ?? null
            );

            return $this->success($result, 'Vault balanced successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}