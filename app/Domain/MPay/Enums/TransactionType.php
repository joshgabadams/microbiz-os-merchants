<?php

namespace App\Domain\MPay\Enums;

enum TransactionType: string
{
    case WALLET_TO_WALLET = 'WALLET_TO_WALLET';

    case WALLET_TO_BANK = 'WALLET_TO_BANK';

    case POS_CASH_IN = 'POS_CASH_IN';

    case POS_CASH_OUT = 'POS_CASH_OUT';

    case MERCHANT_PAYMENT = 'MERCHANT_PAYMENT';
}
