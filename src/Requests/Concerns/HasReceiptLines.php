<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Requests\Concerns;

use TexHub\VirtualPosAndoz\Enums\VatCode;
use TexHub\VirtualPosAndoz\Exceptions\ConfigurationException;
use TexHub\VirtualPosAndoz\Requests\Product;

/**
 * Shared building blocks for receipt and correction-receipt requests:
 * products, payment split, VAT lines, consumer contact and bank fields.
 */
trait HasReceiptLines
{
    /** @var array<int, Product> */
    private array $products = [];

    /** @var array<string, int> map of VatCode value => vatSum */
    private array $vats = [];

    private bool $autoVat = true;

    private ?int $receiptCash = null;
    private ?int $receiptNonCash = null;
    private ?string $consumerContacts = null;
    private ?string $externalId = null;
    private ?string $bankRRN = null;
    private ?string $bankCard = null;

    public function addProduct(Product $product): static
    {
        $this->products[] = $product;

        return $this;
    }

    /**
     * Cash amount paid (минимальные единицы валюты).
     */
    public function cash(int $amount): static
    {
        $this->receiptCash = $amount;

        return $this;
    }

    /**
     * Non-cash (card) amount paid (минимальные единицы валюты).
     */
    public function nonCash(int $amount, ?string $bankRRN = null, ?string $bankCard = null): static
    {
        $this->receiptNonCash = $amount;
        $this->bankRRN = $bankRRN ?? $this->bankRRN;
        $this->bankCard = $bankCard ?? $this->bankCard;

        return $this;
    }

    public function consumer(string $contact): static
    {
        $this->consumerContacts = $contact;

        return $this;
    }

    public function externalId(string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function bank(?string $rrn = null, ?string $card = null): static
    {
        $this->bankRRN = $rrn;
        $this->bankCard = $card;

        return $this;
    }

    /**
     * Add an explicit VAT total for a rate (disables automatic VAT extraction).
     */
    public function vat(VatCode $code, int $sum): static
    {
        $this->autoVat = false;
        $this->vats[$code->value] = ($this->vats[$code->value] ?? 0) + $sum;

        return $this;
    }

    /**
     * Disable automatic VAT calculation without adding an explicit line.
     */
    public function withoutAutoVat(): static
    {
        $this->autoVat = false;

        return $this;
    }

    public function receiptSum(): int
    {
        $total = 0;
        foreach ($this->products as $product) {
            $total += $product->lineSum();
        }

        return $total;
    }

    /**
     * @return array<int, array{vatCode: string, vatSum: int}>
     */
    private function buildVats(): array
    {
        $vats = $this->vats;

        if ($vats === [] && $this->autoVat) {
            // Assume VAT-included prices and extract the embedded tax per rate.
            $byCode = [];
            foreach ($this->products as $product) {
                $byCode[$product->vatCode()->value] = ($byCode[$product->vatCode()->value] ?? 0) + $product->lineSum();
            }
            foreach ($byCode as $code => $gross) {
                $rate = VatCode::from($code)->rate();
                $vats[$code] = $rate > 0 ? (int) round($gross * $rate / (100 + $rate)) : 0;
            }
        }

        $out = [];
        foreach ($vats as $code => $sum) {
            $out[] = ['vatCode' => $code, 'vatSum' => $sum];
        }

        return $out;
    }

    /**
     * Common request parameters shared by receipts and corrections.
     *
     * @return array<string, mixed>
     */
    private function baseBody(): array
    {
        if ($this->products === []) {
            throw new ConfigurationException('A receipt must contain at least one product.');
        }

        if ($this->receiptCash === null && $this->receiptNonCash === null) {
            throw new ConfigurationException('A receipt must specify a cash and/or non-cash amount.');
        }

        $body = [
            'products' => array_map(static fn (Product $p): array => $p->toArray(), $this->products),
            'receiptSum' => $this->receiptSum(),
            'receiptCash' => $this->receiptCash ?? 0,
            'receiptNonCash' => $this->receiptNonCash ?? 0,
            'bankRRN' => $this->bankRRN,
            'bankCard' => $this->bankCard,
            'taxes' => ['vats' => $this->buildVats()],
        ];

        if ($this->consumerContacts !== null) {
            $body['consumerContacts'] = $this->consumerContacts;
        }

        if ($this->externalId !== null) {
            $body['externalId'] = $this->externalId;
        }

        return $body;
    }
}
