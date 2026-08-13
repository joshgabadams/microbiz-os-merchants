<?php

namespace App\Services\TillSession;

use App\Models\Teller;
use App\Models\TillSession;
use App\Services\Branch\BranchBusinessDayService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TillSessionService
{
    public function __construct(
        protected BranchBusinessDayService $branchBusinessDayService
    ) {
    }

    /**
     * Open a till session.
     *
     * A till session may only be opened while the teller's branch
     * has an OPEN business day.
     *
     * Only one OPEN till session may exist for a teller at a time.
     *
     * @throws Exception
     */
    public function open(array $data): TillSession
    {
        return DB::transaction(function () use ($data) {
            $teller = Teller::whereKey($data['teller_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (!$teller->active) {
                throw new Exception(
                    'Cannot open a till session for an inactive teller.'
                );
            }

            $businessDay = $this->branchBusinessDayService
                ->requireOpenDay($teller->branch_id);

            $existing = TillSession::where(
                'teller_id',
                $teller->id
            )
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new Exception(
                    'This teller already has an open session.'
                );
            }

            $openingFloat = (float) $data['opening_float'];

            if ($openingFloat < 0) {
                throw new Exception(
                    'Opening float cannot be negative.'
                );
            }

            return TillSession::create([
                'session_reference' => $this->generateSessionReference(),

                'teller_id' => $teller->id,

                'branch_id' => $teller->branch_id,

                'branch_business_day_id' => $businessDay->id,

                'vault_id' => $teller->vault_id,

                'opened_by' => $data['opened_by'],

                'closed_by' => null,

                'opening_float' => $openingFloat,

                'current_balance' => $openingFloat,

                'expected_cash' => $openingFloat,

                'physical_cash' => null,

                'variance' => 0,

                'status' => 'OPEN',

                'opened_at' => now(),

                'closed_at' => null,
            ]);
        });
    }

    /**
     * Generate a unique till-session reference.
     */
    protected function generateSessionReference(): string
    {
        do {
            $reference = 'TS-'
                . now()->format('YmdHis')
                . '-'
                . strtoupper(Str::random(6));
        } while (
            TillSession::where(
                'session_reference',
                $reference
            )->exists()
        );

        return $reference;
    }
}