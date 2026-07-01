<?php

namespace App\Domain\Finance\Contracts;

use App\Domain\Finance\DTO\FinancialTransactionData;

interface TransactionProcessor
{
    public function process(
        FinancialTransactionData $transaction
    );
}