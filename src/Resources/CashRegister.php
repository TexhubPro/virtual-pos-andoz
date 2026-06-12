<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Enums\FormCode;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Device state and shift lifecycle (состояние кассы, открытие/закрытие смены).
 */
final class CashRegister extends Resource
{
    /**
     * Device status (do not poll more often than every 5–10 minutes).
     */
    public function status(): Response
    {
        return $this->api->call(FormCode::DeviceStatus->command(), FormCode::DeviceStatus->value);
    }

    /**
     * Open a shift.
     *
     * @param string      $cashier    Cashier name (max 64 bytes).
     * @param string|null $externalId Optional unique operation id (≤ 36 ASCII chars).
     */
    public function openShift(string $cashier, ?string $externalId = null): Response
    {
        return $this->api->call(FormCode::OpenShift->command(), FormCode::OpenShift->value, array_filter([
            'cashier' => $cashier,
            'externalId' => $externalId,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Close the shift. The response carries the closing report (see Reports::xReport()).
     *
     * @param string      $cashier    Cashier name (max 64 bytes).
     * @param string|null $externalId Optional unique operation id (≤ 36 ASCII chars).
     */
    public function closeShift(string $cashier, ?string $externalId = null): Response
    {
        return $this->api->call(FormCode::CloseShift->command(), FormCode::CloseShift->value, array_filter([
            'cashier' => $cashier,
            'externalId' => $externalId,
        ], static fn ($v) => $v !== null));
    }
}
