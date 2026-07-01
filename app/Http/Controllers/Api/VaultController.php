<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVaultRequest;
use App\Http\Requests\UpdateVaultRequest;
use App\Models\Vault;
use App\Services\Vault\VaultService;

class VaultController extends Controller
{
    public function __construct(
        protected VaultService $service
    ) {}

    public function index()
    {
        return $this->service->all();
    }

    public function store(StoreVaultRequest $request)
    {
        return $this->service->create(
            $request->validated()
        );
    }

    public function show(Vault $vault)
    {
        return $vault->load([
            'branch',
            'glAccount'
        ]);
    }

    public function update(
        UpdateVaultRequest $request,
        Vault $vault
    ) {
        return $this->service->update(
            $vault,
            $request->validated()
        );
    }

    public function destroy(Vault $vault)
    {
        $this->service->delete($vault);

        return response()->json([
            'message' => 'Vault deleted'
        ]);
    }
}
