<?php

namespace App\Services\Ledger;

use App\Models\CashLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    public function post(array $data)
    {
        return DB::transaction(function () use ($data) {

            $lastBalance = CashLedger::where(
                'teller_id',
                $data['teller_id']
            )->latest('id')->value('running_balance') ?? 0;

            $balance =
                $lastBalance
                + $data['debit']
                - $data['credit'];

            return CashLedger::create([

                'reference_no' => Str::uuid(),

                'branch_id' => $data['branch_id'],

                'vault_id' => $data['vault_id'],

                'teller_id' => $data['teller_id'],

                'user_id' => $data['user_id'],

                'transaction_type' => $data['transaction_type'],

                'source_type' => $data['source_type'],

                'source_id' => $data['source_id'],

                'debit' => $data['debit'],

                'credit' => $data['credit'],

                'running_balance' => $balance,

                'currency' => 'NGN',

                'narration' => $data['narration'],

                'status' => 'APPROVED',

                'transaction_date' => now(),

            ]);
        });
    }
}