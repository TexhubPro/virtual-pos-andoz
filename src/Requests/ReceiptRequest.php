<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Requests;

use TexHub\VirtualPosAndoz\Enums\OperationType;
use TexHub\VirtualPosAndoz\Enums\TaxType;
use TexHub\VirtualPosAndoz\Requests\Concerns\HasReceiptLines;

/**
 * Fluent builder for an operation receipt (`formReceipt` / RECEIPT).
 *
 * ```php
 * $receipt = ReceiptRequest::income(TaxType::General)
 *     ->addProduct(Product::make('Шкаф', 10000, 1))
 *     ->cash(10000)
 *     ->consumer('sim@mail.ru')
 *     ->externalId('7887788778');
 * ```
 */
final class ReceiptRequest
{
    use HasReceiptLines;

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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(
            [
                'operationType' => $this->operationType->value,
                'taxType' => $this->taxType->value,
            ],
            $this->baseBody(),
        );
    }
}
