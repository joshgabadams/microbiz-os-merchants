<?php

namespace App\Listeners;

use App\Events\FinancialTransactionPosted;
use App\Services\Accounting\GlPostingService;

class PostFinancialTransactionToGL
{
    public function __construct(
        protected GlPostingService $glPostingService
    ) {
    }

    public function handle(FinancialTransactionPosted $event): void
    {
        // Temporary logging to verify the event reaches this listener
        logger()->info(
            'Posting transaction to GL',
            [
                'transaction' => $event->transaction
            ]
        );

        // Next step: this event only guarantees a "transaction" occurred \u2014
        // confirm $event->transaction resolves to (or wraps) a CashLedger
        // before wiring the actual post() call, since GlPostingService
        // expects a CashLedger specifically:
        // $this->glPostingService->postFromCashLedger($event->transaction);
    }
}