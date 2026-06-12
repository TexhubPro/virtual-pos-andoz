<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Enums\FormCode;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Fiscal document retrieval (получение фискальных документов).
 */
final class Documents extends Resource
{
    /**
     * Get the last fiscal document.
     */
    public function last(): Response
    {
        return $this->api->call(FormCode::PrintLastFd->command(), FormCode::PrintLastFd->value);
    }

    /**
     * Get a fiscal document by its FD number.
     */
    public function byNumber(int $fdNumber): Response
    {
        return $this->api->call(FormCode::PrintFdByNumber->command(), FormCode::PrintFdByNumber->value, [
            'fdNumber' => $fdNumber,
        ]);
    }

    /**
     * Get a fiscal document by the external id used when it was created.
     */
    public function byExternalId(string $externalId): Response
    {
        return $this->api->call(FormCode::PrintFdByExternalId->command(), FormCode::PrintFdByExternalId->value, [
            'externalId' => $externalId,
        ]);
    }
}
