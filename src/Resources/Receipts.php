<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Enums\FormCode;
use TexHub\VirtualPosAndoz\Requests\CorrectionReceiptRequest;
use TexHub\VirtualPosAndoz\Requests\ReceiptRequest;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Operation receipts and correction receipts (чек операции / чек коррекции).
 */
final class Receipts extends Resource
{
    /**
     * Form an operation receipt.
     *
     * @param ReceiptRequest|array<string, mixed> $request
     */
    public function create(ReceiptRequest|array $request): Response
    {
        $params = $request instanceof ReceiptRequest ? $request->toArray() : $request;

        return $this->api->call(FormCode::Receipt->command(), FormCode::Receipt->value, $params);
    }

    /**
     * Form a correction receipt.
     *
     * @param CorrectionReceiptRequest|array<string, mixed> $request
     */
    public function createCorrection(CorrectionReceiptRequest|array $request): Response
    {
        $params = $request instanceof CorrectionReceiptRequest ? $request->toArray() : $request;

        return $this->api->call(FormCode::CorrectionReceipt->command(), FormCode::CorrectionReceipt->value, $params);
    }
}
