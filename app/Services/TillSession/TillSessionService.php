<?php

namespace App\Services\TillSession;

use App\Models\TillSession;
use App\Models\Teller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TillSessionService
{
    public function open(array $data)
    {
        return DB::transaction(function () use ($data) {

            // Only one open session per teller
            $existing = TillSession::where('teller_id', $data['teller_id'])
                ->where('status', 'OPEN')
                ->first();

            if ($existing) {
                throw new \Exception('This teller already has an open session.');
            }

            // Load teller
            $teller = Teller::findOrFail($data['teller_id']);

            // Create session
            $session = TillSession::create([

                'session_reference' => 'TS-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4)),

                'teller_id' => $teller->id,

                'branch_id' => $teller->branch_id,

                'vault_id' => $teller->vault_id,

                'opened_by' => $data['opened_by'],

                'opening_float' => $data['opening_float'],

                'current_balance' => $data['opening_float'],

                'expected_cash' => $data['opening_float'],

                'physical_cash' => null,

                'variance' => 0,

                'status' => 'OPEN',

                'opened_at' => now()

            ]);

            return $session;
        });
    }
}