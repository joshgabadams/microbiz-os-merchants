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
     * AppServiceProvider::boot() (a union-typed handle() like this one
     * is not picked up by Laravel's listener auto-discovery, so it must
     * be registered manually rather than relying on the type-hint alone).
     */
    public function handle(
        FinancialTransactionCreated|FinancialTransactionPosted|FinancialTransactionReversed $event
    ): void {
        $transaction = $event->transaction;

        $action = match (true) {
            $event instanceof FinancialTransactionCreated => 'created',
            $event instanceof FinancialTransactionPosted => 'posted',
            $event instanceof FinancialTransactionReversed => 'reversed',
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
