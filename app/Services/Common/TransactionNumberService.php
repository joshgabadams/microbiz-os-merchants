<?php

namespace App\Services\Common;

use Illuminate\Support\Facades\DB;

class TransactionNumberService
{
    public function generate(string $prefix): string
    {
        $date = now()->format('Ymd');

        $key = $prefix.'_'.$date;

        $next = DB::transaction(function () use ($key) {

            $record = DB::table('transaction_sequences')
                ->lockForUpdate()
                ->where('sequence_key', $key)
                ->first();

            if (!$record) {

                DB::table('transaction_sequences')
                    ->insert([
                        'sequence_key'=>$key,
                        'last_number'=>1,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);

                return 1;
            }

            $next = $record->last_number + 1;

            DB::table('transaction_sequences')
                ->where('id',$record->id)
                ->update([
                    'last_number'=>$next,
                    'updated_at'=>now()
                ]);

            return $next;

        });

        return sprintf(
            "%s%s%07d",
            $prefix,
            $date,
            $next
        );
    }
}
