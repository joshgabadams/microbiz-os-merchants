<?php

namespace App\Services\Payments;

use App\Models\Merchant;
use App\Models\MerchantTerminal;
use Exception;
use Illuminate\Support\Facades\DB;

class MerchantTerminalService
{
    public function list(Merchant $merchant)
    {
        return $merchant->terminals()->get();
    }

    public function assign(Merchant $merchant, array $data, int $assignedBy): MerchantTerminal
    {
        return DB::transaction(function () use ($merchant, $data, $assignedBy) {
            return $merchant->terminals()->create([
                'merchant_location_id' => $data['merchant_location_id'] ?? null,
                'terminal_id' => $data['terminal_id'],
                'serial_number' => $data['serial_number'],
                'terminal_type' => $data['terminal_type'],
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
                'status' => 'PENDING_ACTIVATION',
                'application_version' => $data['application_version'] ?? null,
                'assigned_by' => $assignedBy,
            ]);
        });
    }

    /**
     * @throws Exception
     */
    public function activate(MerchantTerminal $terminal): MerchantTerminal
    {
        if ($terminal->status === 'ACTIVE') {
            throw new Exception("Terminal {$terminal->terminal_id} is already active.");
        }

        $terminal->update([
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        return $terminal->fresh();
    }

    /**
     * @throws Exception
     */
    public function suspend(MerchantTerminal $terminal): MerchantTerminal
    {
        if ($terminal->status !== 'ACTIVE') {
            throw new Exception("Terminal {$terminal->terminal_id} is not active.");
        }

        $terminal->update([
            'status' => 'SUSPENDED',
        ]);

        return $terminal->fresh();
    }
}
