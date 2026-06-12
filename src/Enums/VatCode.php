<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * VAT rate code (ставка НДС).
 */
enum VatCode: string
{
    /** Стандартная ставка 15%. */
    case Standard = 'STANDARD';
    /** Сниженная ставка 7% (стройка, гостиницы, общепит). */
    case Reduced1 = 'REDUCED1';
    /** Сниженная ставка 5% (сельхоз, обучение, медуслуги в санаториях). */
    case Reduced2 = 'REDUCED2';
    /** Нулевая ставка для экспорта товаров 0%. */
    case ZeroTaxExport = 'ZERO_TAX_EXPORT';
    /** Налоговые освобождения от НДС 0%. */
    case ZeroTax = 'ZERO_TAX';
    /** НДС 2.5% — птицеводство. */
    case Reduced3 = 'REDUCED3';
    /** НДС 10% — переработка пшеницы. */
    case Reduced4 = 'REDUCED4';

    /**
     * Nominal VAT rate as a percentage.
     */
    public function rate(): float
    {
        return match ($this) {
            self::Standard => 15.0,
            self::Reduced1 => 7.0,
            self::Reduced2 => 5.0,
            self::Reduced3 => 2.5,
            self::Reduced4 => 10.0,
            self::ZeroTaxExport, self::ZeroTax => 0.0,
        };
    }
}
