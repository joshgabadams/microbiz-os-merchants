<?php

namespace App\Listeners;

use App\Events\FinancialTransactionPosted;
use App\Services\Ledger\GlPostingService;

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

        // Next step:
        // $this->glPostingService->post(...);
    }
}