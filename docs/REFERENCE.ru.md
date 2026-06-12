# Виртуальная касса (Andoz / ОФД) — Справочник методов

[English](REFERENCE.md) · [Русский](REFERENCE.ru.md) · [← README](../README.ru.md)

Полный справочник по каждому методу `texhub/virtual-pos-andoz`. Каждый метод — это
`POST {baseURL}/api/terminal/{command}` с телом, начинающимся с `formCode`. Любой
вызов возвращает `Response`: `->success()` (rc === SUCCESS), `->get('dot.path')`
читает блок `data`, `->all()` — полный ответ, `->rc` — сырой код результата.

Все денежные значения — в **минимальных единицах валюты** (например, дирамы).

> Во всех примерах используется:
> ```php
> use TexHub\VirtualPosAndoz\VirtualPos;
> $pos = VirtualPos::make('ВАШ_ТОКЕН_ОФД');
> ```

---

## Содержание

- [Касса и смены](#касса-и-смены)
  - [`register()->status()`](#registerstatus)
  - [`register()->openShift()`](#registeropenshift)
  - [`register()->closeShift()`](#registercloseshift)
- [Чеки](#чеки)
  - [`receipts()->create()`](#receiptscreate)
  - [`receipts()->createCorrection()`](#receiptscreatecorrection)
- [Фискальные документы](#фискальные-документы)
  - [`documents()->last()`](#documentslast)
  - [`documents()->byNumber()`](#documentsbynumber)
  - [`documents()->byExternalId()`](#documentsbyexternalid)
- [Отчёты](#отчёты)
  - [`reports()->xReport()`](#reportsxreport)
  - [`reports()->fnReport()`](#reportsfnreport)
  - [`reports()->shiftCashReport()`](#reportsshiftcashreport)
  - [`reports()->queueReport()`](#reportsqueuereport)
- [Наличные для размена](#наличные-для-размена)
  - [`cash()->add()`](#cashadd)
  - [`cash()->remove()`](#cashremove)
- [Универсальные счётчики](#универсальные-счётчики)
  - [`counters()->create()`](#counterscreate)
  - [`counters()->list()`](#counterslist)
  - [`counters()->increase()` / `decrease()`](#countersincrease--decrease)
  - [`counters()->reset()`](#countersreset)
  - [`counters()->delete()`](#countersdelete)
- [Перечисления (Enums)](#перечисления-enums)
- [Коды ошибок](#коды-ошибок)

---

## Касса и смены

### `register()->status()`

Состояние кассы. **Не опрашивайте чаще, чем раз в 5–10 минут.**

- **Endpoint:** `POST /api/terminal/deviceStatus` · **formCode:** `DEVICE_STATUS`
- **Параметры:** нет

```php
$status = $pos->register()->status();
$status->get('shiftStatus');                      // false → смена закрыта
$status->get('exchangeBalance');                  // разменный баланс
$status->get('queueCount');                       // ФД в очереди на ОФД
$status->get('registrationInformation.orgName');
```

**Поля `data`:** `timestamp`, `localTime`, `blocked`, `reasonForBlocking`,
`registered`, `shiftStatus`, `serialNumber`, `fdNumber`, `shiftNumber`, `fnNumber`,
`fnLifetime`, `queueCount`, `onlineStatus`, `exchangeBalance`, `shiftOpeningTime`,
`shiftExpirationTime`, `registrationInformation` (объект), `fnLifetimeInDays`,
`billingInfo` (объект).

```jsonc
{
  "rc": "SUCCESS",
  "data": {
    "timestamp": 1769004574,
    "localTime": "Wed, 21 Jan 2026 14:09:34 +0000",
    "blocked": false,
    "registered": true,
    "shiftStatus": false,
    "serialNumber": "VP01D038P000001",
    "fdNumber": 3,
    "shiftNumber": 1,
    "fnNumber": "B09CF8627B2CC919",
    "queueCount": 0,
    "onlineStatus": true,
    "exchangeBalance": 0,
    "registrationInformation": { "orgName": "ОФД Тест Симонов", "inn": "000000001", "...": "..." },
    "fnLifetimeInDays": 9585
  }
}
```

### `register()->openShift()`

Открытие смены.

- **Endpoint:** `POST /api/terminal/openShift` · **formCode:** `OPEN_SHIFT`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `cashier` | string | M | Кассир (макс. 64 байта). |
| `externalId` | string | O | Уникальный id операции (≤ 36 ASCII). |

```php
$res = $pos->register()->openShift('Бульбашев Б.Б.', '5665566556');
$res->get('fdNumber');     // 6
$res->get('fpd');          // "2810933523"
$res->get('onlineStatus'); // true
```

**Поля `data`:** `fdNumber`, `fpd`, `onlineStatus`.

### `register()->closeShift()`

Закрытие смены; в ответе — итоговый отчёт.

- **Endpoint:** `POST /api/terminal/closeShift` · **formCode:** `CLOSE_SHIFT`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `cashier` | string | M | Кассир (макс. 64 байта). |
| `externalId` | string | O | Уникальный id операции (≤ 36 ASCII). |

```php
$res = $pos->register()->closeShift('Одилчонова З.');
$res->get('fdNumber');                  // 13
$res->get('report.totalReceiptCount');  // 1
$res->get('report.currentShiftCash');   // 10000
```

**Поля `data`:** `fdNumber`, `fpd`, `onlineStatus`, `report` (объект — той же
структуры, что и [`reports()->xReport()`](#reportsxreport)).

---

## Чеки

### `receipts()->create()`

Формирование чека операции. Собирается через `ReceiptRequest` + `Product`.

- **Endpoint:** `POST /api/terminal/formReceipt` · **formCode:** `RECEIPT`

Билдер `ReceiptRequest`:

| Метод | Описание |
|-------|----------|
| `ReceiptRequest::income(TaxType)` / `revertIncome(TaxType)` / `make(OperationType, TaxType)` | Создать билдер. |
| `->addProduct(Product)` | Добавить позицию (можно несколько). |
| `->cash(int)` | Сумма наличными. |
| `->nonCash(int, ?bankRRN, ?bankCard)` | Сумма безналичными (+ банковские поля). |
| `->consumer(string)` | Телефон покупателя (12 цифр, `992…`) или email. |
| `->externalId(string)` | Уникальный id операции (≤ 36 ASCII). |
| `->vat(VatCode, int)` | Явная сумма НДС по ставке (отключает авто-НДС). |
| `->withoutAutoVat()` | Отключить авто-расчёт НДС. |

`Product::make(name, price, quantity = 1, commodity = Goods, vatCode = Standard)`
плюс `->code(?string)` / `->codeFromRaw(string)` (Base64) / `->sum(int)`.

Билдер сам считает `sum` по позиции (`price * quantity`), общий `receiptSum`, и —
если НДС не задан вручную — выделяет НДС из суммы (цена с НДС) по каждой ставке.

```php
use TexHub\VirtualPosAndoz\Enums\{TaxType, VatCode, Commodity};
use TexHub\VirtualPosAndoz\Requests\{Product, ReceiptRequest};

$res = $pos->receipts()->create(
    ReceiptRequest::income(TaxType::General)
        ->addProduct(Product::make('Шкаф', price: 10000, quantity: 1, vatCode: VatCode::Standard))
        ->cash(10000)
        ->consumer('sim@mail.ru')
        ->externalId('7887788778')
);

$res->get('fdNumber');      // 3
$res->get('fpd');           // "0305354977"
$res->get('receiptNumber'); // 1
$res->get('shiftNumber');   // 1
```

**Тело запроса** (формируется за вас):

```jsonc
{
  "formCode": "RECEIPT",
  "operationType": "INCOME",
  "taxType": "GENERAL",
  "products": [
    { "code": null, "name": "Шкаф", "price": 10000, "quantity": 1,
      "commodity": "GOODS", "vatCode": "STANDARD", "sum": 10000 }
  ],
  "receiptSum": 10000,
  "receiptCash": 10000,
  "receiptNonCash": 0,
  "bankRRN": null,
  "bankCard": null,
  "taxes": { "vats": [ { "vatCode": "STANDARD", "vatSum": 1304 } ] },
  "consumerContacts": "sim@mail.ru",
  "externalId": "7887788778"
}
```

**Поля `data`:** `fdNumber`, `fpd`, `onlineStatus`, `shiftNumber`, `receiptNumber`,
`counters` (массив).

### `receipts()->createCorrection()`

Формирование чека коррекции. Собирается через `CorrectionReceiptRequest`.

- **Endpoint:** `POST /api/terminal/formCorrectionReceipt` · **formCode:** `CORRECTION_RECEIPT`

Тот же билдер, что и `ReceiptRequest`, **плюс** обязательное основание коррекции:

| Метод | Описание |
|-------|----------|
| `->self(int $timestamp, string $orderNumber)` | Самостоятельная (`SELF`). |
| `->forced(int $timestamp, string $orderNumber)` | По предписанию (`FORCED`). |

`$timestamp` — дата постановления (Unix-секунды; время нормализуется к 00:00:00).

```php
use TexHub\VirtualPosAndoz\Requests\CorrectionReceiptRequest;

$res = $pos->receipts()->createCorrection(
    CorrectionReceiptRequest::income(TaxType::General)
        ->self(timestamp: 1731332618, orderNumber: '123Ж-Э1')
        ->addProduct(Product::make('Шкаф', 10000, 1))
        ->cash(10000)
        ->vat(VatCode::Standard, 10)
);

$res->get('fdNumber');  // 22
```

**Поля `data`:** `fdNumber`, `fpd`, `onlineStatus`, `shiftNumber`, `receiptNumber`,
`counters` (массив).

---

## Фискальные документы

В ответе — те же поля, что и в бумажном ФД. Конкретный набор зависит от типа
документа. Если ФД нет в локальном хранилище, копию можно получить в кабинете ОФД.

### `documents()->last()`

- **Endpoint:** `POST /api/terminal/getLastFD` · **formCode:** `PRINT_LAST_FD`
- **Параметры:** нет

```php
$fd = $pos->documents()->last();
$fd->get('fdNumber');                 // 23
$fd->get('report.totalReceiptCount');
```

**Поля `data`:** `fdNumber`, `fpd`, `onlineStatus`, `report` (объект).

### `documents()->byNumber()`

- **Endpoint:** `POST /api/terminal/getFDByNumber` · **formCode:** `PRINT_FD_BY_NUMBER`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `fdNumber` | int | M | Номер фискального документа. |

```php
$fd = $pos->documents()->byNumber(9);
$fd->get('formCode');      // "RECEIPT"
$fd->get('operationType'); // "INCOME"
$fd->get('receiptSum');    // 10000
$fd->get('fpd');           // "2732104564"
```

**Поля `data`:** зависят от типа ФД — для чека: `formCode`, `ffdVersion`,
`operationType`, `taxType`, `consumerContacts`, `products[]`, `receiptSum`,
`receiptCash`, `receiptNonCash`, `bankRRN`, `bankCard`, `bankAuthCode`, `bankCardName`,
`bankResult`, `taxes.vats[]`, `cashier`, `timeStamp`, `shiftNumber`, `receiptNumber`,
`fdNumber`, `fpd`, `changeSum`, а также реквизиты организации/регистрации.

### `documents()->byExternalId()`

- **Endpoint:** `POST /api/terminal/getFDByExternalID` · **formCode:** `PRINT_FD_BY_EXTERNAL_ID`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `externalId` | string | M | Внешний id операции (≤ 36 ASCII). |

> Внешний id **не** передаётся в кабинет ОФД — поиск только локальный.

```php
$fd = $pos->documents()->byExternalId('5665566556');
$fd->get('formCode');    // "OPEN_SHIFT"
$fd->get('externalId');  // "5665566556"
```

**Поля `data`:** `ffdVersion`, `inn`, `ein`, `kpp`, `timeStamp`, `cashier`,
`shiftNumber`, `fdNumber`, `fnNumber`, `fpd`, `formCode`, `externalId` (значение
`formCode` совпадает с типом оригинального документа).

---

## Отчёты

### `reports()->xReport()`

Промежуточный (X) отчёт по текущей смене.

- **Endpoint:** `POST /api/terminal/getXReport` · **formCode:** `GET_X_REPORT`
- **Параметры:** нет

```php
$x = $pos->reports()->xReport();
$x->get('shiftNumber');
$x->get('totalReceiptCount');
$x->get('currentShiftCash');
foreach ($x->get('receiptsInfo', []) as $row) {
    $row['receiptType'];                       // INCOME / REVERT_INCOME / …
    $row['receiptTotalCounter']['Amount'];
}
```

**Поля `data`:** `inn`, `ein`, `kpp`, `cashier`, `currentTime`, `shiftNumber`,
`kku`, `totalReceiptCount`, `totalCorrectionReceiptCount`, `receiptsInfo[]`,
`receiptsCorrectionInfo[]`, `currentShiftCash`, `counters[]`. Каждый элемент
`receiptsInfo` / `receiptsCorrectionInfo` содержит `receiptType` и
`receiptTotalCounter` (`Type`, `IsCorrection`, `Amount`, `Cash`, `NonCash`, `Count`,
`VatStandard`, `VatReduced1`, `VatReduced2`, `VatZeroTaxExport`, `VatZeroTax`,
`VatReduced3`, `VatReduced4`).

### `reports()->fnReport()`

Отчёт о состоянии Виртуальной кассы (ФН) — структура как у X-отчёта.

- **Endpoint:** `POST /api/terminal/getFNReport` · **formCode:** `GET_FN_REPORT`
- **Параметры:** нет

### `reports()->shiftCashReport()`

Операции инкассации по смене.

- **Endpoint:** `POST /api/terminal/getShiftCashReport` · **formCode:** `GET_SHIFT_CASH_REPORT`
- **Параметры:** нет

```php
$r = $pos->reports()->shiftCashReport();
$r->get('TotalCash');         // 11000
$r->get('ManualCashTotal');   // 1000 (внесено для размена)
$r->get('ReceiptCashTotal');  // 10000 (получено при оплатах)
foreach ($r->get('Operations', []) as $op) {
    $op['Amount']; $op['OperationType']; $op['Timestamp'];
}
```

**Поля `data`:** `Operations[]` (`Amount`, `OperationType`, `Timestamp`,
`ShiftNumber`, `CashierName`), `ManualCashTotal`, `ReceiptCashTotal`, `TotalCash`,
`GeneratedAt`.

### `reports()->queueReport()`

Суммарные данные по документам в очереди, ещё не отправленным в ОФД.

- **Endpoint:** `POST /api/terminal/getQueueReport` · **formCode:** `GET_QUEUE_REPORT`
- **Параметры:** нет

**Поля `data`:** структура как у X-отчёта (с `currentShiftCash`).

---

## Наличные для размена

Разменный баланс не может стать меньше нуля и обнуляется только при закрытии ФН.
Текущий баланс — в [`register()->status()`](#registerstatus) (`exchangeBalance`).

### `cash()->add()`

- **Endpoint:** `POST /api/terminal/addCash` · **formCode:** `ADD_CASH`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `addAmount` | int | M | Вносимая сумма (> 0). |

```php
$pos->cash()->add(10000);   // { "rc": "SUCCESS" }
```

**Ответ:** только результат (`->success()`).

### `cash()->remove()`

- **Endpoint:** `POST /api/terminal/removeCash` · **formCode:** `REMOVE_CASH`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `removeAmount` | int | M | Изымаемая сумма (> 0, ≤ баланс). |

```php
$pos->cash()->remove(5000);
```

**Ответ:** только результат (`->success()`).

---

## Универсальные счётчики

До **32** счётчиков.

### `counters()->create()`

- **Endpoint:** `POST /api/terminal/createCounter` · **formCode:** `CREATE_COUNTER`

```php
use TexHub\VirtualPosAndoz\Enums\CounterValueType;

$pos->counters()->create(
    code: 101,
    title: 'Отмененные операции - количество',
    valueType: CounterValueType::Long,   // или ::Float
    start: 0,
    autoReset: true,                     // сброс при закрытии смены
);
```

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `code` | int | M | Произвольный номер счётчика. |
| `title` | string | M | Название (макс. 50 знаков). |
| `valueType` | `CounterValueType` | M | `LONG` или `FLOAT`. |
| `start` | int\|float | M | Начальное значение. |
| `autoReset` | bool | M | Авто-сброс по закрытию смены. |

**Ответ:** только результат.

### `counters()->list()`

- **Endpoint:** `POST /api/terminal/listCounters` · **formCode:** `LIST_COUNTERS`

```php
$list = $pos->counters()->list();
foreach (($list->all()['data'] ?? []) as $c) {
    $c['counterCode']; $c['counterTitle']; $c['valueType'];
}
```

> Примечание: этот endpoint возвращает `data` как JSON-**массив**; читайте его через
> `$list->all()['data']`.

### `counters()->increase()` / `decrease()`

- **Endpoints:** `increaseCounter` / `decreaseCounter` · **formCodes:** `INCREASE_COUNTER` / `DECREASE_COUNTER`

| Параметр | Тип | Об. | Описание |
|----------|-----|-----|----------|
| `code` | int | M | Номер счётчика. |
| `value` | int\|float | M | Дельта (отрицательное значение инвертирует операцию). |

```php
$pos->counters()->increase(101, 3);
$pos->counters()->decrease(101, 1);
```

**Ответ:** только результат.

### `counters()->reset()`

- **Endpoint:** `POST /api/terminal/resetCounter` · **formCode:** `RESET_COUNTER`

```php
$pos->counters()->reset(101);
```

**Ответ:** только результат.

### `counters()->delete()`

- **Endpoint:** `POST /api/terminal/deleteCounter` · **formCode:** `DELETE_COUNTER`

```php
$pos->counters()->delete(101);
```

**Ответ:** только результат.

---

## Перечисления (Enums)

| Enum | Значения |
|------|----------|
| `OperationType` | `Income`, `RevertIncome`, `Expenditure`, `RevertExpenditure` |
| `TaxType` | `General`, `Simplified1`…`Simplified6`, `Special` (`->label()`) |
| `Commodity` | `Goods`, `Service`, `Job`, `Advance` |
| `VatCode` | `Standard` (15%), `Reduced1` (7%), `Reduced2` (5%), `ZeroTaxExport` (0%), `ZeroTax` (0%), `Reduced3` (2.5%), `Reduced4` (10%) — `->rate()` |
| `CorrectionType` | `Self`, `Forced` |
| `CounterValueType` | `Long`, `Float` |
| `FormCode` | все form-коды (`->command()` → сегмент endpoint) |
| `ResultCode` | `Success` + все коды ошибок (`->message()`) |
| `Environment` | `Production` |

---

## Коды ошибок

Любой `rc`, отличный от `SUCCESS`, бросает `ApiException` (`->rc`, `->code()`,
`->getMessage()`, `->is(ResultCode|string)`). Коды из ПРИЛОЖЕНИЯ 1:

| Код | Значение |
|-----|----------|
| `CASH_NOT_ENOUGH` | Недостаточно наличных для возврата/расхода. |
| `CORE_IS_BLOCKED` | Виртуальная касса заблокирована. |
| `CORE_IS_NOT_ACTIVATED` | Виртуальная касса не активирована. |
| `BALANCE_IS_NEGATIVE` | Отрицательный разменный баланс. |
| `DATETIME_ERROR` | Ошибка даты/времени — проверьте настройки. |
| `DOCUMENT_SIZE_EXCEEDED` | ФД превысил 31500 байт. |
| `DUPLICATED_EXTERNAL_ID` | `externalId` уже использован. |
| `FD_NOT_FOUND` | Фискальный документ не найден. |
| `FISCAL_MODULE_ERROR` | Ошибка Виртуальной кассы. |
| `FISCAL_MODULE_EXPIRED` | Срок действия ФН вышел. |
| `INVALID_BANK_RRN` | Отсутствует/некорректный РРН. |
| `INVALID_CONSUMER_CONTACT` | Ошибка в поле с контактами. |
| `INVALID_COSTOM_MESSAGE` | Ошибка в произвольном сообщении. |
| `INVALID_DOC` | Некорректный документ. |
| `INVALID_EXTERNAL_ID` | `externalId` превышает длину. |
| `INVALID_PRODUCT_CODE` | Ошибка декодирования `code`. |
| `KEYS_ERROR` | Ошибка ключей. |
| `RECEIPT_SUM_TOO_HIGH` | Сумма чека превышает лимит. |
| `SHIFT_MUST_BE_CLOSED` | Смена должна быть закрыта. |
| `SHIFT_MUST_BE_OPENED` | Смена должна быть открыта. |
| `SHIFT_TOO_LONG` | Смена открыта более 24 часов. |
| `VALUE_IS_NEGATIVE` | Значение меньше ноля. |
| `UNIVERSAL_COUNTER_ERROR` | Ошибка при работе со счётчиком. |
| `UNKNOWN_ERROR` | Неизвестная ошибка. |
| `WRONG_LEN_LOG` | Некорректный документ. |

```php
use TexHub\VirtualPosAndoz\Exceptions\ApiException;
use TexHub\VirtualPosAndoz\Enums\ResultCode;

try {
    $pos->receipts()->create($receipt);
} catch (ApiException $e) {
    if ($e->is(ResultCode::ShiftMustBeOpened)) {
        $pos->register()->openShift('Кассир');
    }
}
```
