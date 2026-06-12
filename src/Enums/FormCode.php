<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * All API form codes and their matching endpoint command segments.
 *
 * Every request is a POST to `/api/terminal/{command}` with a body that begins
 * with `{"formCode": "<value>"}`.
 */
enum FormCode: string
{
    case DeviceStatus = 'DEVICE_STATUS';
    case OpenShift = 'OPEN_SHIFT';
    case CloseShift = 'CLOSE_SHIFT';
    case Receipt = 'RECEIPT';
    case CorrectionReceipt = 'CORRECTION_RECEIPT';
    case PrintLastFd = 'PRINT_LAST_FD';
    case PrintFdByNumber = 'PRINT_FD_BY_NUMBER';
    case PrintFdByExternalId = 'PRINT_FD_BY_EXTERNAL_ID';
    case GetXReport = 'GET_X_REPORT';
    case AddCash = 'ADD_CASH';
    case RemoveCash = 'REMOVE_CASH';
    case GetFnReport = 'GET_FN_REPORT';
    case GetShiftCashReport = 'GET_SHIFT_CASH_REPORT';
    case GetQueueReport = 'GET_QUEUE_REPORT';
    case CreateCounter = 'CREATE_COUNTER';
    case ListCounters = 'LIST_COUNTERS';
    case IncreaseCounter = 'INCREASE_COUNTER';
    case DecreaseCounter = 'DECREASE_COUNTER';
    case ResetCounter = 'RESET_COUNTER';
    case DeleteCounter = 'DELETE_COUNTER';

    /**
     * The endpoint command segment used in the URL (`/api/terminal/{command}`).
     */
    public function command(): string
    {
        return match ($this) {
            self::DeviceStatus => 'deviceStatus',
            self::OpenShift => 'openShift',
            self::CloseShift => 'closeShift',
            self::Receipt => 'formReceipt',
            self::CorrectionReceipt => 'formCorrectionReceipt',
            self::PrintLastFd => 'getLastFD',
            self::PrintFdByNumber => 'getFDByNumber',
            self::PrintFdByExternalId => 'getFDByExternalID',
            self::GetXReport => 'getXReport',
            self::AddCash => 'addCash',
            self::RemoveCash => 'removeCash',
            self::GetFnReport => 'getFNReport',
            self::GetShiftCashReport => 'getShiftCashReport',
            self::GetQueueReport => 'getQueueReport',
            self::CreateCounter => 'createCounter',
            self::ListCounters => 'listCounters',
            self::IncreaseCounter => 'increaseCounter',
            self::DecreaseCounter => 'decreaseCounter',
            self::ResetCounter => 'resetCounter',
            self::DeleteCounter => 'deleteCounter',
        };
    }
}
