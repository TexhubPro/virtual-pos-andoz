<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Tests\Support;

use TexHub\VirtualPosAndoz\Http\RawResponse;
use TexHub\VirtualPosAndoz\Http\Transport;

/**
 * In-memory transport for tests: records requests, returns queued responses.
 */
final class FakeTransport implements Transport
{
    /** @var array<int, array{method: string, url: string, headers: array<string,string>, json: ?array<string,mixed>}> */
    public array $history = [];

    /** @var array<int, RawResponse> */
    private array $queue = [];

    public function __construct(
        private readonly int $defaultStatus = 200,
        private readonly string $defaultBody = '{"rc":"SUCCESS"}',
    ) {
    }

    /**
     * Queue a SUCCESS response with the given data block.
     *
     * @param array<string, mixed> $data
     */
    public function push(array $data = [], int $status = 200): self
    {
        return $this->pushBody(['rc' => 'SUCCESS', 'data' => $data], $status);
    }

    /**
     * Queue an error response with the given result code.
     */
    public function pushError(string $rc, int $status = 200): self
    {
        return $this->pushBody(['rc' => $rc], $status);
    }

    /**
     * Queue a raw response body.
     *
     * @param array<string, mixed> $body
     */
    public function pushBody(array $body, int $status = 200): self
    {
        $this->queue[] = new RawResponse($status, (string) json_encode($body));

        return $this;
    }

    public function request(string $method, string $url, array $headers = [], ?array $json = null): RawResponse
    {
        $this->history[] = compact('method', 'url', 'headers', 'json');

        return $this->queue !== [] ? array_shift($this->queue) : new RawResponse($this->defaultStatus, $this->defaultBody);
    }

    /**
     * @return array{method: string, url: string, headers: array<string,string>, json: ?array<string,mixed>}
     */
    public function last(): array
    {
        return $this->history[count($this->history) - 1];
    }

    public function lastUrl(): string
    {
        return $this->last()['url'];
    }

    /**
     * @return array<string, mixed>
     */
    public function lastBody(): array
    {
        return $this->last()['json'] ?? [];
    }
}
