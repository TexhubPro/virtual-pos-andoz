<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Exceptions;

use TexHub\VirtualPosAndoz\Enums\ResultCode;

/**
 * Thrown when the Virtual POS API returns a result code other than SUCCESS
 * (or a non-2xx HTTP status).
 */
class ApiException extends VirtualPosException
{
    /**
     * @param string               $rc         The returned result code (e.g. SHIFT_MUST_BE_OPENED).
     * @param int                  $httpStatus HTTP status code.
     * @param array<string, mixed> $payload    Full decoded response body.
     */
    public function __construct(
        string $message,
        public readonly string $rc,
        public readonly int $httpStatus = 0,
        public readonly array $payload = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromResponse(int $httpStatus, array $body): self
    {
        $rc = (string) ($body['rc'] ?? ResultCode::UnknownError->value);

        return new self(
            message: ResultCode::describe($rc),
            rc: $rc,
            httpStatus: $httpStatus,
            payload: $body,
        );
    }

    /**
     * The result code as an enum case, or null when it is not a documented code.
     */
    public function code(): ?ResultCode
    {
        return ResultCode::tryFrom($this->rc);
    }

    public function is(ResultCode|string $code): bool
    {
        return $this->rc === ($code instanceof ResultCode ? $code->value : $code);
    }
}
