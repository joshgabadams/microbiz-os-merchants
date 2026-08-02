<?php

namespace App\Listeners;

use App\Events\FinancialTransactionCreated;
use App\Events\FinancialTransactionPosted;
use App\Events\FinancialTransactionReversed;
use App\Services\Audit\AuditLogger;

class UpdateAuditTrail
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {
    }

    /**
     * Registered explicitly for all three financial events in
     * AppServiceProvider::boot(). The parameter is deliberately typed as
     * plain `object`, not a union of the three event classes -- testing
     * showed Laravel's listener auto-discovery *does* pick up the first
     * type in a union type-hint and registers it a second time on top of
     * our manual Event::listen() calls, causing FinancialTransactionCreated
     * (the first type in the union) to fire this listener twice per event,
     * while Posted/Reversed correctly fired once. Using `object` here
     * means auto-discovery can't resolve a single event class from the
     * signature, so only the explicit registration in AppServiceProvider
     * applies. The instanceof checks below still work exactly as before.
     */
    public function handle(object $event): void
    {
        $transaction = $event->transaction;

        $action = match (true) {
            $event instanceof FinancialTransactionCreated => 'created',
            $event instanceof FinancialTransactionPosted => 'posted',
            $event instanceof FinancialTransactionReversed => 'reversed',
            default => 'unknown',
        };

        if (! is_object($transaction) || ! method_exists($transaction, 'getAttributes')) {
            // Nothing model-like to attach the audit entry to; log the
            // raw payload so the trail isn't silently dropped.
            $this->auditLogger->log(
                action: "financial_transaction.{$action}",
                metadata: ['raw_transaction' => $transaction],
                module: 'fincore',
            );

            return;
        }

        $this->auditLogger->log(
            action: strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($transaction))).".{$action}",
            subject: $transaction,
            after: $action !== 'reversed' ? $transaction->getAttributes() : null,
            before: $action === 'reversed' ? $transaction->getAttributes() : null,
            module: 'fincore',
        );
    }
}
