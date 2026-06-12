<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Responses;

use ArrayAccess;
use TexHub\VirtualPosAndoz\Enums\ResultCode;

/**
 * Generic wrapper around a Virtual POS response (`{ "rc": "...", "data": {...} }`).
 *
 * `get()` reads (dot-path) inside the `data` block; `all()` returns the full body.
 *
 * @implements ArrayAccess<string, mixed>
 */
class Response implements ArrayAccess
{
    /**
     * @param array<string, mixed> $attributes The decoded `data` block.
     * @param array<string, mixed> $raw        The full decoded response body.
     */
    public function __construct(
        public readonly string $rc,
        protected readonly array $attributes = [],
        protected readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $body Full decoded response body.
     */
    public static function fromResponse(array $body): static
    {
        $data = $body['data'] ?? [];

        return new static(
            (string) ($body['rc'] ?? ''),
            is_array($data) ? $data : ['value' => $data],
            $body,
        );
    }

    public function success(): bool
    {
        return $this->rc === ResultCode::Success->value;
    }

    /**
     * The `data` block of the response.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->attributes;
    }

    /**
     * The full, untouched response body (including `rc`).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->raw;
    }

    /**
     * Read a value from the `data` block by key or dot-path.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        $value = $this->attributes;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }
}
