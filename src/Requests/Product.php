<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Requests;

use TexHub\VirtualPosAndoz\Enums\Commodity;
use TexHub\VirtualPosAndoz\Enums\VatCode;

/**
 * A single receipt line (product / position).
 *
 * `price` and `sum` are in the minimal currency units (e.g. dirams).
 * `sum` defaults to `price * quantity` (rounded) when not given explicitly.
 */
final class Product
{
    private ?int $sum = null;
    private ?string $code = null;

    public function __construct(
        private readonly string $name,
        private readonly int $price,
        private readonly int|float $quantity = 1,
        private readonly Commodity $commodity = Commodity::Goods,
        private readonly VatCode $vatCode = VatCode::Standard,
    ) {
    }

    public static function make(
        string $name,
        int $price,
        int|float $quantity = 1,
        Commodity $commodity = Commodity::Goods,
        VatCode $vatCode = VatCode::Standard,
    ): self {
        return new self($name, $price, $quantity, $commodity, $vatCode);
    }

    /**
     * Set the marking / barcode value already encoded (raw string sent as-is).
     */
    public function code(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Set the marking / barcode from raw scanned data; it is Base64-encoded for you.
     */
    public function codeFromRaw(string $raw): self
    {
        $this->code = base64_encode($raw);

        return $this;
    }

    /**
     * Override the computed line total (минимальные единицы валюты).
     */
    public function sum(int $sum): self
    {
        $this->sum = $sum;

        return $this;
    }

    public function vatCode(): VatCode
    {
        return $this->vatCode;
    }

    public function lineSum(): int
    {
        return $this->sum ?? (int) round($this->price * $this->quantity);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'commodity' => $this->commodity->value,
            'vatCode' => $this->vatCode->value,
            'sum' => $this->lineSum(),
        ];
    }
}
