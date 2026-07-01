<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teller;
use App\Services\Teller\TellerService;
use Illuminate\Http\Request;

class TellerController extends Controller
{
    public function __construct(
        protected TellerService $service
    ) {}

    public function index()
    {
        return $this->service->all();
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

            'status' => 'required'

        ]);

        return $this->service->create($validated);
    }

    public function show(Teller $teller)
    {
        return $teller;
    }

    public function update(Request $request, Teller $teller)
    {
        $teller->update($request->all());

        return $teller;
    }

    public function destroy(Teller $teller)
    {
        $teller->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }
}