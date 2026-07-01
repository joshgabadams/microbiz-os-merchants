<?php

namespace App\Domain\Finance\Enums;

enum FinancialTransactionType: string
{
    case CASH_DEPOSIT = 'CASH_DEPOSIT';

    case CASH_WITHDRAWAL = 'CASH_WITHDRAWAL';

    case TELLER_FLOAT = 'TELLER_FLOAT';

    case FLOAT_RETURN = 'FLOAT_RETURN';

    case VAULT_TRANSFER = 'VAULT_TRANSFER';

    case CUSTOMER_TRANSFER = 'CUSTOMER_TRANSFER';

    case GL_ADJUSTMENT = 'GL_ADJUSTMENT';

    case LOAN_DISBURSEMENT = 'LOAN_DISBURSEMENT';

    case LOAN_REPAYMENT = 'LOAN_REPAYMENT';

    case ATM_LOAD = 'ATM_LOAD';

    case ATM_UNLOAD = 'ATM_UNLOAD';
}