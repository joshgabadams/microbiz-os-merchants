<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teller\OpenTellerRequest;
use App\Http\Requests\Teller\CloseTellerRequest;
use App\Models\Teller;
use App\Services\Teller\TellerService;
use App\Services\Teller\TellerTransactionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class TellerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TellerService $service,
        protected TellerTransactionService $tellerTransactionService
    ) {
    }

    public function index()
    {
        return $this->success(
            $this->service->all(),
            'Tellers retrieved successfully.'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'vault_id' => 'required|exists:vaults,id',
            'gl_account_id' => 'required|exists:gl_accounts,id',
            'teller_code' => 'required|unique:tellers',
            'display_name' => 'required',
            'daily_limit' => 'required|numeric',
            'opening_cash_limit' => 'required|numeric',
            'minimum_cash' => 'required|numeric',
            'maximum_cash' => 'required|numeric',
            'active' => 'required|boolean',
            'status' => 'required',
        ]);

        return $this->success(
            $this->service->create($validated),
            'Teller created successfully.',
            201
        );
    }

    public function show(Teller $teller)
    {
        return $this->success(
            $teller,
            'Teller retrieved successfully.'
        );
    }

    public function update(Request $request, Teller $teller)
    {
        $teller->update($request->all());

        return $this->success(
            $teller->fresh(),
            'Teller updated successfully.'
        );
    }

    public function destroy(Teller $teller)
    {
        $teller->delete();

        return $this->success(
            null,
            'Teller deleted successfully.'
        );
    }

    public function open(OpenTellerRequest $request)
    {
        try {
            $teller = Teller::findOrFail($request->teller_id);

            $transaction = $this->tellerTransactionService->openTeller(
            $teller,
            $request->user()->id,
            $request->reference,
            $request->narration
        );

            return $this->success(
                $transaction,
                'Teller opened successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function close(CloseTellerRequest $request)
    {
        try {
            $teller = Teller::findOrFail($request->teller_id);

            $transaction = $this->tellerTransactionService->closeTeller(
            $teller,
            $request->user()->id,
            $request->reference,
            $request->narration
        );

            return $this->success(
                $transaction,
                'Teller closed successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}