<?php

namespace App\Domain\Finance\DTO;

use App\Domain\Finance\Enums\FinancialTransactionType;

class FinancialTransactionData
{
    public function __construct(
        public FinancialTransactionType $type,

        public float $amount,

        public string $currency,

        public ?int $sourceId,

        public ?int $destinationId,

        public int $performedBy,

        public ?string $reference = null,

        public ?string $narration = null,
    ) {
    }
}