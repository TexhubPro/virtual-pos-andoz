<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * Correction receipt type (тип коррекции).
 */
enum CorrectionType: string
{
    /** Самостоятельная операция. */
    case Self = 'SELF';
    /** Операция по предписанию налогового органа. */
    case Forced = 'FORCED';
}
