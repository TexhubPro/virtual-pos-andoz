<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Requests;

use TexHub\VirtualPosAndoz\Enums\CorrectionType;
use TexHub\VirtualPosAndoz\Enums\OperationType;
use TexHub\VirtualPosAndoz\Enums\TaxType;
use TexHub\VirtualPosAndoz\Exceptions\ConfigurationException;
use TexHub\VirtualPosAndoz\Requests\Concerns\HasReceiptLines;

/**
 * Fluent builder for a correction receipt (`formCorrectionReceipt` / CORRECTION_RECEIPT).
 *
 * ```php
 * $correction = CorrectionReceiptRequest::income(TaxType::General)
 *     ->self(1731332618, '123Ж-Э1')
 *     ->addProduct(Product::make('Шкаф', 10000, 1))
 *     ->cash(10000);
 * ```
 */
final class CorrectionReceiptRequest
{
    use HasReceiptLines;

    private CorrectionType $correctionType = CorrectionType::Self;
    private ?int $reasonTimestamp = null;
    private ?string $reasonOrderNumber = null;

    public function __construct(
        private readonly OperationType $operationType,
        private readonly TaxType $taxType,
    ) {
    }

    public static function make(OperationType $operationType, TaxType $taxType): self
    {
        return new self($operationType, $taxType);
    }

    public static function income(TaxType $taxType = TaxType::General): self
    {
        return new self(OperationType::Income, $taxType);
    }

    public static function revertIncome(TaxType $taxType = TaxType::General): self
    {
        return new self(OperationType::RevertIncome, $taxType);
    }

    /**
     * Self-initiated correction with the order date/number basis.
     *
     * @param int $timestamp Date of the decree (only the date is used; time is 00:00:00). Unix seconds.
     */
    public function self(int $timestamp, string $orderNumber): self
    {
        $this->correctionType = CorrectionType::Self;
        $this->reasonTimestamp = $timestamp;
        $this->reasonOrderNumber = $orderNumber;

        return $this;
    }

    /**
     * Correction forced by the tax authority.
     *
     * @param int $timestamp Date of the decree. Unix seconds.
     */
    public function forced(int $timestamp, string $orderNumber): self
    {
        $this->correctionType = CorrectionType::Forced;
        $this->reasonTimestamp = $timestamp;
        $this->reasonOrderNumber = $orderNumber;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->reasonTimestamp === null || $this->reasonOrderNumber === null) {
            throw new ConfigurationException('A correction receipt requires a correction reason (timestamp and order number).');
        }

        return array_merge(
            [
                'operationType' => $this->operationType->value,
                'taxType' => $this->taxType->value,
                'correctionType' => $this->correctionType->value,
                'correctionReason' => [
                    'timestamp' => $this->reasonTimestamp,
                    'orderNumber' => $this->reasonOrderNumber,
                ],
            ],
            $this->baseBody(),
        );
    }
}
