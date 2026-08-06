<?php

namespace App\Domain\MPay\Enums;

/**
 * Full lifecycle per the M-PAY Agency Banking Blueprint §6. The primary
 * chain runs PROSPECT -> ... -> ACTIVE; REJECTED/RESTRICTED/SUSPENDED/
 * DORMANT/TERMINATED/EXPIRED/BLACKLISTED are alternative/terminal states
 * reachable from various points in that chain, never a linear "next" step.
 */
enum AgentStatus: string
{
    case PROSPECT = 'PROSPECT';

    case DRAFT = 'DRAFT';

    case PENDING_KYC = 'PENDING_KYC';

    case PENDING_LOCATION_VERIFICATION = 'PENDING_LOCATION_VERIFICATION';

    case PENDING_COMPLIANCE_REVIEW = 'PENDING_COMPLIANCE_REVIEW';

    case PENDING_APPROVAL = 'PENDING_APPROVAL';

    case APPROVED = 'APPROVED';

    case AGREEMENT_PENDING = 'AGREEMENT_PENDING';

    case TRAINING_PENDING = 'TRAINING_PENDING';

    case TERMINAL_PENDING = 'TERMINAL_PENDING';

    case ACTIVE = 'ACTIVE';

    case REJECTED = 'REJECTED';

    case RESTRICTED = 'RESTRICTED';

    case SUSPENDED = 'SUSPENDED';

    case DORMANT = 'DORMANT';

    case TERMINATED = 'TERMINATED';

    case EXPIRED = 'EXPIRED';

    case BLACKLISTED = 'BLACKLISTED';
}
