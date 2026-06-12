<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz;

use TexHub\VirtualPosAndoz\Enums\Environment;
use TexHub\VirtualPosAndoz\Http\ApiClient;
use TexHub\VirtualPosAndoz\Http\CurlTransport;
use TexHub\VirtualPosAndoz\Http\Transport;
use TexHub\VirtualPosAndoz\Resources\Cash;
use TexHub\VirtualPosAndoz\Resources\CashRegister;
use TexHub\VirtualPosAndoz\Resources\Counters;
use TexHub\VirtualPosAndoz\Resources\Documents;
use TexHub\VirtualPosAndoz\Resources\Receipts;
use TexHub\VirtualPosAndoz\Resources\Reports;

/**
 * Entry point of the Virtual POS (ОФД / Andoz) SDK.
 *
 * Framework-agnostic: construct directly, or resolve from the Laravel container
 * via the {@see \TexHub\VirtualPosAndoz\Laravel\VirtualPos} facade.
 *
 * ```php
 * $pos = VirtualPos::make('YOUR_TOKEN');
 *
 * $pos->register()->openShift('Бульбашев Б.Б.');
 *
 * $receipt = $pos->receipts()->create(
 *     ReceiptRequest::income(TaxType::General)
 *         ->addProduct(Product::make('Шкаф', 10000, 1))
 *         ->cash(10000)
 * );
 *
 * echo $receipt->get('fpd');
 * ```
 */
final class VirtualPos
{
    private readonly Transport $transport;
    private readonly ApiClient $api;

    /** @var array<string, object> */
    private array $resources = [];

    public function __construct(
        private readonly Config $config,
        ?Transport $transport = null,
    ) {
        $this->transport = $transport ?? new CurlTransport($config->timeout);
        $this->api = new ApiClient($config, $this->transport);
    }

    public static function make(
        string $token,
        Environment $environment = Environment::Production,
        ?Transport $transport = null,
    ): self {
        return new self(new Config($token, $environment), $transport);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config, ?Transport $transport = null): self
    {
        return new self(Config::fromArray($config), $transport);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function api(): ApiClient
    {
        return $this->api;
    }

    /** Device status and shift open/close. */
    public function register(): CashRegister
    {
        return $this->resource(CashRegister::class);
    }

    /** Operation receipts and corrections. */
    public function receipts(): Receipts
    {
        return $this->resource(Receipts::class);
    }

    /** Fiscal document retrieval. */
    public function documents(): Documents
    {
        return $this->resource(Documents::class);
    }

    /** X / FN / shift-cash / queue reports. */
    public function reports(): Reports
    {
        return $this->resource(Reports::class);
    }

    /** Cash for change: add / remove. */
    public function cash(): Cash
    {
        return $this->resource(Cash::class);
    }

    /** Universal counters. */
    public function counters(): Counters
    {
        return $this->resource(Counters::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function resource(string $class): object
    {
        /** @var T */
        return $this->resources[$class] ??= new $class($this->api, $this->config);
    }
}
