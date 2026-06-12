<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Enums\FormCode;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Reports: X-report, FN status report, shift cash (инкассация) and queue report.
 */
final class Reports extends Resource
{
    /**
     * Intermediate (X) report.
     */
    public function xReport(): Response
    {
        return $this->api->call(FormCode::GetXReport->command(), FormCode::GetXReport->value);
    }

    /**
     * Virtual cash register (FN) state report.
     */
    public function fnReport(): Response
    {
        return $this->api->call(FormCode::GetFnReport->command(), FormCode::GetFnReport->value);
    }

    /**
     * Shift cash / collection (инкассация) operations report.
     */
    public function shiftCashReport(): Response
    {
        return $this->api->call(FormCode::GetShiftCashReport->command(), FormCode::GetShiftCashReport->value);
    }

    /**
     * Report of documents queued but not yet delivered to the ОФД.
     */
    public function queueReport(): Response
    {
        return $this->api->call(FormCode::GetQueueReport->command(), FormCode::GetQueueReport->value);
    }
}
