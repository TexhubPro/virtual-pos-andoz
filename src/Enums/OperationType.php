<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * Receipt operation type (тип операции).
 */
enum OperationType: string
{
    /** Приход. */
    case Income = 'INCOME';
    /** Возврат прихода. */
    case RevertIncome = 'REVERT_INCOME';
    /** Расход. */
    case Expenditure = 'EXPENDITURE';
    /** Возврат расхода. */
    case RevertExpenditure = 'REVERT_EXPENDITURE';
}
