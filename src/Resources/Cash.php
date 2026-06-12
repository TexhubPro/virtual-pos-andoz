<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Enums\FormCode;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Cash management for change (внесение / изъятие наличных для размена).
 *
 * The balance can never go below zero. Read the current balance from
 * {@see CashRegister::status()} (`exchangeBalance`) or the X/FN reports.
 */
final class Cash extends Resource
{
    /**
     * Add cash for change.
     *
     * @param int $amount Amount to add (> 0), in minimal currency units.
     */
    public function add(int $amount): Response
    {
        return $this->api->call(FormCode::AddCash->command(), FormCode::AddCash->value, [
            'addAmount' => $amount,
        ]);
    }

    /**
     * Remove cash for change.
     *
     * @param int $amount Amount to remove (> 0), in minimal currency units.
     */
    public function remove(int $amount): Response
    {
        return $this->api->call(FormCode::RemoveCash->command(), FormCode::RemoveCash->value, [
            'removeAmount' => $amount,
        ]);
    }
}
