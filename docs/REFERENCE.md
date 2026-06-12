# Virtual POS (Andoz / ОФД) — Method Reference

[English](REFERENCE.md) · [Русский](REFERENCE.ru.md) · [← README](../README.md)

Full reference for every method in `texhub/virtual-pos-andoz`. Each method maps to
a `POST {baseURL}/api/terminal/{command}` call with a `formCode` body. Every call
returns a `Response`: `->success()` (rc === SUCCESS), `->get('dot.path')` reads the
`data` block, `->all()` returns the full body, `->rc` is the raw result code.

All money values are in **minimal currency units** (e.g. dirams).

> Setup used in all examples:
> ```php
> use TexHub\VirtualPosAndoz\VirtualPos;
> $pos = VirtualPos::make('YOUR_OFD_TOKEN');
> ```

---

## Contents

- [Cash register & shifts](#cash-register--shifts)
  - [`register()->status()`](#registerstatus)
  - [`register()->openShift()`](#registeropenshift)
  - [`register()->closeShift()`](#registercloseshift)
- [Receipts](#receipts)
  - [`receipts()->create()`](#receiptscreate)
  - [`receipts()->createCorrection()`](#receiptscreatecorrection)
- [Fiscal documents](#fiscal-documents)
  - [`documents()->last()`](#documentslast)
  - [`documents()->byNumber()`](#documentsbynumber)
  - [`documents()->byExternalId()`](#documentsbyexternalid)
- [Reports](#reports)
  - [`reports()->xReport()`](#reportsxreport)
  - [`reports()->fnReport()`](#reportsfnreport)
  - [`reports()->shiftCashReport()`](#reportsshiftcashreport)
  - [`reports()->queueReport()`](#reportsqueuereport)
- [Cash for change](#cash-for-change)
  - [`cash()->add()`](#cashadd)
  - [`cash()->remove()`](#cashremove)
- [Universal counters](#universal-counters)
  - [`counters()->create()`](#counterscreate)
  - [`counters()->list()`](#counterslist)
  - [`counters()->increase()` / `decrease()`](#countersincrease--decrease)
  - [`counters()->reset()`](#countersreset)
  - [`counters()->delete()`](#countersdelete)
- [Enums](#enums)
- [Error codes](#error-codes)

---

## Cash register & shifts

### `register()->status()`

Device status. **Do not poll more often than every 5–10 minutes.**

- **Endpoint:** `POST /api/terminal/deviceStatus` · **formCode:** `DEVICE_STATUS`
- **Parameters:** none

```php
$status = $pos->register()->status();
$status->get('shiftStatus');                      // false → shift closed
$status->get('exchangeBalance');                  // change balance
$status->get('queueCount');                       // FDs awaiting ОФД
$status->get('registrationInformation.orgName');
```

**Response `data` fields:** `timestamp`, `localTime`, `blocked`, `reasonForBlocking`,
`registered`, `shiftStatus`, `serialNumber`, `fdNumber`, `shiftNumber`, `fnNumber`,
`fnLifetime`, `queueCount`, `onlineStatus`, `exchangeBalance`, `shiftOpeningTime`,
`shiftExpirationTime`, `registrationInformation` (object), `fnLifetimeInDays`,
`billingInfo` (object).

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

Open a shift.

- **Endpoint:** `POST /api/terminal/openShift` · **formCode:** `OPEN_SHIFT`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `cashier` | string | M | Cashier name (max 64 bytes). |
| `externalId` | string | O | Unique operation id (≤ 36 ASCII chars). |

```php
$res = $pos->register()->openShift('Бульбашев Б.Б.', '5665566556');
$res->get('fdNumber');     // 6
$res->get('fpd');          // "2810933523"
$res->get('onlineStatus'); // true
```

**Response `data`:** `fdNumber`, `fpd`, `onlineStatus`.

### `register()->closeShift()`

Close the shift; the response includes the closing report.

- **Endpoint:** `POST /api/terminal/closeShift` · **formCode:** `CLOSE_SHIFT`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `cashier` | string | M | Cashier name (max 64 bytes). |
| `externalId` | string | O | Unique operation id (≤ 36 ASCII chars). |

```php
$res = $pos->register()->closeShift('Одилчонова З.');
$res->get('fdNumber');                                   // 13
$res->get('report.totalReceiptCount');                   // 1
$res->get('report.currentShiftCash');                    // 10000
```

**Response `data`:** `fdNumber`, `fpd`, `onlineStatus`, `report` (object — same shape
as [`reports()->xReport()`](#reportsxreport)).

---

## Receipts

### `receipts()->create()`

Form an operation receipt. Build it with `ReceiptRequest` + `Product`.

- **Endpoint:** `POST /api/terminal/formReceipt` · **formCode:** `RECEIPT`

`ReceiptRequest` builder:

| Method | Description |
|--------|-------------|
| `ReceiptRequest::income(TaxType)` / `revertIncome(TaxType)` / `make(OperationType, TaxType)` | Create the builder. |
| `->addProduct(Product)` | Add a line (repeatable). |
| `->cash(int)` | Cash amount. |
| `->nonCash(int, ?bankRRN, ?bankCard)` | Non-cash amount (+ bank fields). |
| `->consumer(string)` | Buyer phone (12 digits, `992…`) or email. |
| `->externalId(string)` | Unique operation id (≤ 36 ASCII chars). |
| `->vat(VatCode, int)` | Explicit VAT total for a rate (disables auto VAT). |
| `->withoutAutoVat()` | Disable automatic VAT extraction. |

`Product::make(name, price, quantity = 1, commodity = Goods, vatCode = Standard)`
plus `->code(?string)` / `->codeFromRaw(string)` (Base64) / `->sum(int)`.

The builder computes each line `sum` (`price * quantity`), the `receiptSum`
(total), and — unless you set VAT explicitly — extracts VAT-included tax per rate.

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

**Resulting request body** (sent for you):

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

**Response `data`:** `fdNumber`, `fpd`, `onlineStatus`, `shiftNumber`, `receiptNumber`,
`counters` (array).

### `receipts()->createCorrection()`

Form a correction receipt. Build it with `CorrectionReceiptRequest`.

- **Endpoint:** `POST /api/terminal/formCorrectionReceipt` · **formCode:** `CORRECTION_RECEIPT`

Same builder as `ReceiptRequest` **plus** a required correction reason:

| Method | Description |
|--------|-------------|
| `->self(int $timestamp, string $orderNumber)` | Self-initiated (`SELF`). |
| `->forced(int $timestamp, string $orderNumber)` | By tax authority (`FORCED`). |

`$timestamp` is the decree date in Unix seconds (time is normalized to 00:00:00).

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

**Response `data`:** `fdNumber`, `fpd`, `onlineStatus`, `shiftNumber`, `receiptNumber`,
`counters` (array).

---

## Fiscal documents

The response carries the same fields a printed FD contains. The exact set depends on
the document type. If a FD is missing locally, a copy can be obtained in the ОФД cabinet.

### `documents()->last()`

- **Endpoint:** `POST /api/terminal/getLastFD` · **formCode:** `PRINT_LAST_FD`
- **Parameters:** none

```php
$fd = $pos->documents()->last();
$fd->get('fdNumber');                 // 23
$fd->get('report.totalReceiptCount');
```

**Response `data`:** `fdNumber`, `fpd`, `onlineStatus`, `report` (object).

### `documents()->byNumber()`

- **Endpoint:** `POST /api/terminal/getFDByNumber` · **formCode:** `PRINT_FD_BY_NUMBER`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `fdNumber` | int | M | Fiscal document number. |

```php
$fd = $pos->documents()->byNumber(9);
$fd->get('formCode');     // "RECEIPT"
$fd->get('operationType');// "INCOME"
$fd->get('receiptSum');   // 10000
$fd->get('fpd');          // "2732104564"
```

**Response `data`:** depends on FD type — for a receipt: `formCode`, `ffdVersion`,
`operationType`, `taxType`, `consumerContacts`, `products[]`, `receiptSum`,
`receiptCash`, `receiptNonCash`, `bankRRN`, `bankCard`, `bankAuthCode`, `bankCardName`,
`bankResult`, `taxes.vats[]`, `cashier`, `timeStamp`, `shiftNumber`, `receiptNumber`,
`fdNumber`, `fpd`, `changeSum`, plus org/registration fields.

### `documents()->byExternalId()`

- **Endpoint:** `POST /api/terminal/getFDByExternalID` · **formCode:** `PRINT_FD_BY_EXTERNAL_ID`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `externalId` | string | M | External operation id (≤ 36 ASCII chars). |

> The external id is **not** sent to the ОФД cabinet — it only resolves locally.

```php
$fd = $pos->documents()->byExternalId('5665566556');
$fd->get('formCode');    // "OPEN_SHIFT"
$fd->get('externalId');  // "5665566556"
```

**Response `data`:** `ffdVersion`, `inn`, `ein`, `kpp`, `timeStamp`, `cashier`,
`shiftNumber`, `fdNumber`, `fnNumber`, `fpd`, `formCode`, `externalId` (the `formCode`
matches the original document type).

---

## Reports

### `reports()->xReport()`

Intermediate (X) report for the current shift.

- **Endpoint:** `POST /api/terminal/getXReport` · **formCode:** `GET_X_REPORT`
- **Parameters:** none

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

**Response `data`:** `inn`, `ein`, `kpp`, `cashier`, `currentTime`, `shiftNumber`,
`kku`, `totalReceiptCount`, `totalCorrectionReceiptCount`, `receiptsInfo[]`,
`receiptsCorrectionInfo[]`, `currentShiftCash`, `counters[]`. Each `receiptsInfo` /
`receiptsCorrectionInfo` item has `receiptType` and a `receiptTotalCounter`
(`Type`, `IsCorrection`, `Amount`, `Cash`, `NonCash`, `Count`, `VatStandard`,
`VatReduced1`, `VatReduced2`, `VatZeroTaxExport`, `VatZeroTax`, `VatReduced3`,
`VatReduced4`).

### `reports()->fnReport()`

Virtual cash register (FN) state report — same shape as the X-report.

- **Endpoint:** `POST /api/terminal/getFNReport` · **formCode:** `GET_FN_REPORT`
- **Parameters:** none

### `reports()->shiftCashReport()`

Cash collection (инкассация) operations for the shift.

- **Endpoint:** `POST /api/terminal/getShiftCashReport` · **formCode:** `GET_SHIFT_CASH_REPORT`
- **Parameters:** none

```php
$r = $pos->reports()->shiftCashReport();
$r->get('TotalCash');         // 11000
$r->get('ManualCashTotal');   // 1000 (added for change)
$r->get('ReceiptCashTotal');  // 10000 (from payments)
foreach ($r->get('Operations', []) as $op) {
    $op['Amount']; $op['OperationType']; $op['Timestamp'];
}
```

**Response `data`:** `Operations[]` (`Amount`, `OperationType`, `Timestamp`,
`ShiftNumber`, `CashierName`), `ManualCashTotal`, `ReceiptCashTotal`, `TotalCash`,
`GeneratedAt`.

### `reports()->queueReport()`

Aggregated totals of documents queued but not yet delivered to the ОФД.

- **Endpoint:** `POST /api/terminal/getQueueReport` · **formCode:** `GET_QUEUE_REPORT`
- **Parameters:** none

**Response `data`:** same shape as the X-report (with `currentShiftCash`).

---

## Cash for change

The change balance can never go below zero, and resets only on FN close. Read the
current balance from [`register()->status()`](#registerstatus) (`exchangeBalance`).

### `cash()->add()`

- **Endpoint:** `POST /api/terminal/addCash` · **formCode:** `ADD_CASH`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `addAmount` | int | M | Amount to add (> 0). |

```php
$pos->cash()->add(10000);   // { "rc": "SUCCESS" }
```

**Response:** result only (`->success()`).

### `cash()->remove()`

- **Endpoint:** `POST /api/terminal/removeCash` · **formCode:** `REMOVE_CASH`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `removeAmount` | int | M | Amount to remove (> 0, ≤ balance). |

```php
$pos->cash()->remove(5000);
```

**Response:** result only (`->success()`).

---

## Universal counters

Up to **32** counters.

### `counters()->create()`

- **Endpoint:** `POST /api/terminal/createCounter` · **formCode:** `CREATE_COUNTER`

```php
use TexHub\VirtualPosAndoz\Enums\CounterValueType;

$pos->counters()->create(
    code: 101,
    title: 'Отмененные операции - количество',
    valueType: CounterValueType::Long,   // or ::Float
    start: 0,
    autoReset: true,                     // reset on shift close
);
```

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `code` | int | M | Free-form counter id. |
| `title` | string | M | Title (max 50 chars). |
| `valueType` | `CounterValueType` | M | `LONG` or `FLOAT`. |
| `start` | int\|float | M | Initial value. |
| `autoReset` | bool | M | Auto-reset on shift close. |

**Response:** result only.

### `counters()->list()`

- **Endpoint:** `POST /api/terminal/listCounters` · **formCode:** `LIST_COUNTERS`

```php
$list = $pos->counters()->list();
foreach ($list->get('value', $list->all()['data'] ?? []) as $c) {
    $c['counterCode']; $c['counterTitle']; $c['valueType'];
}
```

> Note: this endpoint returns `data` as a JSON **array**; read it via
> `$list->all()['data']`.

### `counters()->increase()` / `decrease()`

- **Endpoints:** `increaseCounter` / `decreaseCounter` · **formCodes:** `INCREASE_COUNTER` / `DECREASE_COUNTER`

| Parameter | Type | Req. | Description |
|-----------|------|------|-------------|
| `code` | int | M | Counter id. |
| `value` | int\|float | M | Delta (negatives invert the operation). |

```php
$pos->counters()->increase(101, 3);
$pos->counters()->decrease(101, 1);
```

**Response:** result only.

### `counters()->reset()`

- **Endpoint:** `POST /api/terminal/resetCounter` · **formCode:** `RESET_COUNTER`

```php
$pos->counters()->reset(101);
```

**Response:** result only.

### `counters()->delete()`

- **Endpoint:** `POST /api/terminal/deleteCounter` · **formCode:** `DELETE_COUNTER`

```php
$pos->counters()->delete(101);
```

**Response:** result only.

---

## Enums

| Enum | Cases |
|------|-------|
| `OperationType` | `Income`, `RevertIncome`, `Expenditure`, `RevertExpenditure` |
| `TaxType` | `General`, `Simplified1`…`Simplified6`, `Special` (`->label()`) |
| `Commodity` | `Goods`, `Service`, `Job`, `Advance` |
| `VatCode` | `Standard` (15%), `Reduced1` (7%), `Reduced2` (5%), `ZeroTaxExport` (0%), `ZeroTax` (0%), `Reduced3` (2.5%), `Reduced4` (10%) — `->rate()` |
| `CorrectionType` | `Self`, `Forced` |
| `CounterValueType` | `Long`, `Float` |
| `FormCode` | every form code (`->command()` → endpoint segment) |
| `ResultCode` | `Success` + all error codes (`->message()`) |
| `Environment` | `Production` |

---

## Error codes

Any `rc` other than `SUCCESS` throws `ApiException` (`->rc`, `->code()`, `->getMessage()`,
`->is(ResultCode|string)`). Codes from ПРИЛОЖЕНИЕ 1:

| Code | Meaning |
|------|---------|
| `CASH_NOT_ENOUGH` | Not enough cash for a revert/expenditure. |
| `CORE_IS_BLOCKED` | Virtual cash register is blocked. |
| `CORE_IS_NOT_ACTIVATED` | Virtual cash register is not activated. |
| `BALANCE_IS_NEGATIVE` | Negative change balance. |
| `DATETIME_ERROR` | Date/time error — check settings. |
| `DOCUMENT_SIZE_EXCEEDED` | FD exceeded the 31500-byte limit. |
| `DUPLICATED_EXTERNAL_ID` | `externalId` already used. |
| `FD_NOT_FOUND` | Fiscal document not found. |
| `FISCAL_MODULE_ERROR` | Virtual cash register error. |
| `FISCAL_MODULE_EXPIRED` | FN lifetime expired. |
| `INVALID_BANK_RRN` | Missing/invalid RRN. |
| `INVALID_CONSUMER_CONTACT` | Invalid contacts field. |
| `INVALID_COSTOM_MESSAGE` | Invalid custom message. |
| `INVALID_DOC` | Invalid document. |
| `INVALID_EXTERNAL_ID` | `externalId` too long. |
| `INVALID_PRODUCT_CODE` | Failed to decode `code`. |
| `KEYS_ERROR` | Keys error. |
| `RECEIPT_SUM_TOO_HIGH` | Receipt sum over the limit. |
| `SHIFT_MUST_BE_CLOSED` | Shift must be closed. |
| `SHIFT_MUST_BE_OPENED` | Shift must be open. |
| `SHIFT_TOO_LONG` | Shift open more than 24 hours. |
| `VALUE_IS_NEGATIVE` | Value below zero. |
| `UNIVERSAL_COUNTER_ERROR` | Counter error. |
| `UNKNOWN_ERROR` | Unknown error. |
| `WRONG_LEN_LOG` | Invalid document. |

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
