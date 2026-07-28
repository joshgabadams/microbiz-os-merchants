<?php

namespace App\Listeners;

use App\Events\FinancialTransactionCreated;
use App\Events\FinancialTransactionPosted;
use App\Events\FinancialTransactionReversed;
use App\Models\User;
use App\Services\Notification\NotificationService;

class NotifyFinancialUsers
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Registered explicitly for all three financial events in
     * AppServiceProvider::boot() (see UpdateAuditTrail for why).
     *
     * Starts conservative: only notifies on reversals, since those are
     * the exception case staff most need to know about immediately.
     * Extend the match() below once deposit/withdrawal/float notification
     * templates and recipient rules are defined.
     */
    public function handle(
        FinancialTransactionCreated|FinancialTransactionPosted|FinancialTransactionReversed $event
    ): void {
        if (! $event instanceof FinancialTransactionReversed) {
            return;
        }

        $transaction = $event->transaction;
        $performedBy = is_object($transaction) ? ($transaction->performed_by ?? null) : null;

        if (! $performedBy) {
            return;
        }

        $user = User::find($performedBy);

        if (! $user) {
            return;
        }

        $this->notificationService->send(
            notifiable: $user,
            channel: 'mail',
            template: 'financial_transaction.reversed',
            payload: [
                'subject' => 'A transaction you processed was reversed',
                'body' => 'A transaction you processed has been reversed. Please review it in the system.',
            ],
        );
    }
}
