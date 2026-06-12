<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * Subject of calculation (предмет расчёта).
 */
enum Commodity: string
{
    /** Товар. */
    case Goods = 'GOODS';
    /** Услуга. */
    case Service = 'SERVICE';
    /** Выполнение работ. */
    case Job = 'JOB';
    /** Авансовый платёж. */
    case Advance = 'ADVANCE';
}
