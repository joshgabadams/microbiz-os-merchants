<?php

namespace App\Domain\MPay\Enums;

enum MerchantStatus: string
{
    case DRAFT = 'DRAFT';

    case PENDING_KYC = 'PENDING_KYC';

    case PENDING_REVIEW = 'PENDING_REVIEW';

    case APPROVED = 'APPROVED';

    case ACTIVE = 'ACTIVE';

    case REJECTED = 'REJECTED';

    case SUSPENDED = 'SUSPENDED';

    case RESTRICTED = 'RESTRICTED';

    case DEACTIVATED = 'DEACTIVATED';

    case CLOSED = 'CLOSED';
}
