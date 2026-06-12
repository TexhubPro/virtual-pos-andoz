<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * Stored value type of a universal counter (тип хранимых данных счётчика).
 */
enum CounterValueType: string
{
    /** Целочисленные значения. */
    case Long = 'LONG';
    /** Числа с плавающей запятой. */
    case Float = 'FLOAT';
}
