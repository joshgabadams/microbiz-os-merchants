<?php

namespace App\Domain\MPay\Enums;

enum TransactionStatus: string
{
    case RECEIVED = 'RECEIVED';

    case VALIDATING = 'VALIDATING';

    case PENDING_AUTHORIZATION = 'PENDING_AUTHORIZATION';

    case AUTHORIZED = 'AUTHORIZED';

    case PROCESSING = 'PROCESSING';

    case PENDING_FINCORE = 'PENDING_FINCORE';

    case SUCCESSFUL = 'SUCCESSFUL';

    case FAILED = 'FAILED';

    case REVERSED = 'REVERSED';

    case EXPIRED = 'EXPIRED';

    case CANCELLED = 'CANCELLED';
}
