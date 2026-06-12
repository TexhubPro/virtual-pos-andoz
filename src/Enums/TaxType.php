<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * Taxation regime / system of taxation (тип налогообложения, СНО).
 */
enum TaxType: string
{
    /** Общий режим. */
    case General = 'GENERAL';
    /** УСН 6%. */
    case Simplified1 = 'SIMPLIFIED1';
    /** УСН 13%. */
    case Simplified2 = 'SIMPLIFIED2';
    /** УСН 18%. */
    case Simplified3 = 'SIMPLIFIED3';
    /** Спец режим. */
    case Special = 'SPECIAL';
    /** УСН (-50%) — 3%. */
    case Simplified4 = 'SIMPLIFIED4';
    /** УСН (-50%) — 6,5%. */
    case Simplified5 = 'SIMPLIFIED5';
    /** УСН (-50%) — 9%. */
    case Simplified6 = 'SIMPLIFIED6';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Общий режим',
            self::Simplified1 => 'УСН 6%',
            self::Simplified2 => 'УСН 13%',
            self::Simplified3 => 'УСН 18%',
            self::Special => 'Спец режим',
            self::Simplified4 => 'УСН (-50%) — 3%',
            self::Simplified5 => 'УСН (-50%) — 6,5%',
            self::Simplified6 => 'УСН (-50%) — 9%',
        };
    }
}
