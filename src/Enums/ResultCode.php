<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * The `rc` (result code) returned by every API call.
 *
 * `SUCCESS` means the command was processed; any other value is an error
 * (see ПРИЛОЖЕНИЕ 1. КОДЫ ОШИБОК of the protocol specification).
 */
enum ResultCode: string
{
    case Success = 'SUCCESS';

    case CashNotEnough = 'CASH_NOT_ENOUGH';
    case CoreIsBlocked = 'CORE_IS_BLOCKED';
    case CoreIsNotActivated = 'CORE_IS_NOT_ACTIVATED';
    case BalanceIsNegative = 'BALANCE_IS_NEGATIVE';
    case DatetimeError = 'DATETIME_ERROR';
    case DocumentSizeExceeded = 'DOCUMENT_SIZE_EXCEEDED';
    case DuplicatedExternalId = 'DUPLICATED_EXTERNAL_ID';
    case FdNotFound = 'FD_NOT_FOUND';
    case FiscalModuleError = 'FISCAL_MODULE_ERROR';
    case FiscalModuleExpired = 'FISCAL_MODULE_EXPIRED';
    case InvalidBankRrn = 'INVALID_BANK_RRN';
    case InvalidConsumerContact = 'INVALID_CONSUMER_CONTACT';
    case InvalidCustomMessage = 'INVALID_COSTOM_MESSAGE';
    case InvalidDoc = 'INVALID_DOC';
    case InvalidExternalId = 'INVALID_EXTERNAL_ID';
    case InvalidProductCode = 'INVALID_PRODUCT_CODE';
    case KeysError = 'KEYS_ERROR';
    case ReceiptSumTooHigh = 'RECEIPT_SUM_TOO_HIGH';
    case ShiftMustBeClosed = 'SHIFT_MUST_BE_CLOSED';
    case ShiftMustBeOpened = 'SHIFT_MUST_BE_OPENED';
    case ShiftTooLong = 'SHIFT_TOO_LONG';
    case ValueIsNegative = 'VALUE_IS_NEGATIVE';
    case UniversalCounterError = 'UNIVERSAL_COUNTER_ERROR';
    case UnknownError = 'UNKNOWN_ERROR';
    case WrongLenLog = 'WRONG_LEN_LOG';

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }

    /**
     * Human-readable description (Russian, per the protocol specification).
     */
    public function message(): string
    {
        return match ($this) {
            self::Success => 'Команда обработана успешно.',
            self::CashNotEnough => 'Недостаточно наличных средств в кассе для выполнения операции «Возврат прихода» или «Расход».',
            self::CoreIsBlocked => 'Виртуальная касса заблокирована.',
            self::CoreIsNotActivated => 'Виртуальная касса не активирована.',
            self::BalanceIsNegative => 'Отрицательный разменный баланс.',
            self::DatetimeError => 'Ошибка при работе с датой и временем: проверьте настройки.',
            self::DocumentSizeExceeded => 'Фискальный документ превысил максимальный размер 31500 байт.',
            self::DuplicatedExternalId => 'Идентификатор externalId уже был использован.',
            self::FdNotFound => 'Фискальный документ не найден.',
            self::FiscalModuleError => 'Ошибка в работе Виртуальной кассы.',
            self::FiscalModuleExpired => 'Срок действия виртуального фискального накопителя вышел.',
            self::InvalidBankRrn => 'Отсутствует или некорректный РРН.',
            self::InvalidConsumerContact => 'Ошибка в поле с контактами.',
            self::InvalidCustomMessage => 'Ошибка в произвольном сообщении.',
            self::InvalidDoc => 'Некорректный документ.',
            self::InvalidExternalId => 'Идентификатор externalId превышает допустимую длину.',
            self::InvalidProductCode => 'Ошибка при декодировании поля code.',
            self::KeysError => 'Ошибка ключей.',
            self::ReceiptSumTooHigh => 'Сумма в чеке превышает допустимый лимит.',
            self::ShiftMustBeClosed => 'Смена должна быть закрыта.',
            self::ShiftMustBeOpened => 'Смена должна быть открыта.',
            self::ShiftTooLong => 'Смена открыта более 24 часов.',
            self::ValueIsNegative => 'Недопустимое значение меньше ноля.',
            self::UniversalCounterError => 'Ошибка при работе со счетчиком.',
            self::UnknownError => 'Неизвестная ошибка.',
            self::WrongLenLog => 'Некорректный документ.',
        };
    }

    /**
     * Resolve a description for any raw rc value, including unknown ones.
     */
    public static function describe(string $rc): string
    {
        return self::tryFrom($rc)?->message() ?? ('Ошибка Виртуальной кассы: ' . $rc);
    }
}
