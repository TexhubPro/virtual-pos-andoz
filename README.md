# TexHub · Virtual POS (Andoz / ОФД)

**🌐 English** · [Русский](README.ru.md)

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

A clean, framework-agnostic PHP SDK for the **Virtual POS / fiscal cash register API** (ОФД / Andoz, `vkassa-api.ofd.tj`) — device status, shifts, receipts & corrections, fiscal documents, X/FN/cash reports, cash in/out and universal counters — with first-class **Laravel** support.

> 📖 **Full method reference** (every method, parameters & example responses): [`docs/REFERENCE.md`](docs/REFERENCE.md) · [Русский](docs/REFERENCE.ru.md)

## ✨ Features

- 🧾 **Receipts & corrections** with a fluent builder (auto `receiptSum`, auto VAT)
- 🔄 **Shift lifecycle** — status, open, close
- 📄 **Fiscal documents** — last, by FD number, by external id
- 📊 **Reports** — X-report, FN report, shift cash (инкассация), queue
- 💵 **Cash for change** — add / remove
- 🔢 **Universal counters** — create, list, increase, decrease, reset, delete
- 🎯 Typed **enums** (operation type, tax type, commodity, VAT, result codes)
- 🧪 Fully **unit-tested**, no network needed (injectable transport)
- ⚙️ **Laravel** service provider, facade and publishable config

## Requirements

- PHP `^8.2`, `ext-curl`, `ext-json`
- Laravel 11/12/13 (optional)

## Installation

```bash
composer require texhub/virtual-pos-andoz
```

## Quick start (any PHP project)

```php
use TexHub\VirtualPosAndoz\VirtualPos;
use TexHub\VirtualPosAndoz\Enums\TaxType;
use TexHub\VirtualPosAndoz\Requests\Product;
use TexHub\VirtualPosAndoz\Requests\ReceiptRequest;

$pos = VirtualPos::make('YOUR_OFD_TOKEN');

// 1) Open a shift
$pos->register()->openShift('Бульбашев Б.Б.');

// 2) Issue a cash income receipt
$receipt = $pos->receipts()->create(
    ReceiptRequest::income(TaxType::General)
        ->addProduct(Product::make('Шкаф', price: 10000, quantity: 1))
        ->cash(10000)
        ->consumer('sim@mail.ru')
        ->externalId('7887788778')
);

echo $receipt->get('fdNumber');   // 3
echo $receipt->get('fpd');        // 0305354977

// 3) Close the shift
$pos->register()->closeShift('Бульбашев Б.Б.');
```

> All money values are in **minimal currency units** (e.g. dirams). `price` and
> `quantity` produce the line `sum` automatically (`price * quantity`), and
> `receiptSum` is the total of all lines.

## Authentication

The token issued by your ОФД operator is sent in the `Authorization` header.
By default the raw token is sent; if your operator expects a scheme, set a prefix:

```php
use TexHub\VirtualPosAndoz\Config;
use TexHub\VirtualPosAndoz\VirtualPos;

$pos = new VirtualPos(new Config(token: 'TOKEN', authPrefix: 'Bearer'));
```

## Receipts

```php
use TexHub\VirtualPosAndoz\Enums\{TaxType, VatCode, Commodity};
use TexHub\VirtualPosAndoz\Requests\{Product, ReceiptRequest};

$receipt = ReceiptRequest::income(TaxType::General)
    ->addProduct(
        Product::make('Гречка', price: 2500, quantity: 2, commodity: Commodity::Goods, vatCode: VatCode::Standard)
            ->codeFromRaw('4880230146327')   // marking/barcode → Base64
    )
    ->addProduct(Product::make('Доставка', price: 1000, vatCode: VatCode::Reduced1))
    ->cash(6000)
    // ->nonCash(6000, bankRRN: '123456789012', bankCard: '123456****1234')
    ->consumer('992900123456')
    ->externalId('ORD-100032');

$pos->receipts()->create($receipt);
```

**VAT** — by default the SDK extracts VAT-included tax per rate. To send exact
amounts yourself, call `->vat(VatCode::Standard, 1228)` (disables auto VAT), or
`->withoutAutoVat()`.

### Corrections

```php
use TexHub\VirtualPosAndoz\Requests\CorrectionReceiptRequest;

$pos->receipts()->createCorrection(
    CorrectionReceiptRequest::income(TaxType::General)
        ->self(timestamp: 1731332618, orderNumber: '123Ж-Э1')  // or ->forced(...)
        ->addProduct(Product::make('Шкаф', 10000, 1))
        ->cash(10000)
);
```

## Documents, reports, cash & counters

```php
$pos->documents()->last();
$pos->documents()->byNumber(9);
$pos->documents()->byExternalId('5665566556');

$pos->reports()->xReport();
$pos->reports()->fnReport();
$pos->reports()->shiftCashReport();
$pos->reports()->queueReport();

$pos->cash()->add(10000);
$pos->cash()->remove(5000);

$pos->counters()->create(101, 'Отмененные операции');
$pos->counters()->list();
$pos->counters()->increase(101, 3);
$pos->counters()->decrease(101, 1);
$pos->counters()->reset(101);
$pos->counters()->delete(101);
```

## Reading responses

Every call returns a `Response`. `get()` reads (dot-path) inside the `data`
block; `all()` returns the full body, `success()` checks `rc === SUCCESS`.

```php
$status = $pos->register()->status();
$status->get('shiftStatus');         // bool
$status->get('exchangeBalance');     // change balance
$status->get('registrationInformation.orgName');
```

## Error handling

A result code other than `SUCCESS` throws an `ApiException`:

```php
use TexHub\VirtualPosAndoz\Exceptions\ApiException;
use TexHub\VirtualPosAndoz\Enums\ResultCode;

try {
    $pos->receipts()->create($receipt);
} catch (ApiException $e) {
    $e->rc;                       // 'SHIFT_MUST_BE_OPENED'
    $e->code();                   // ResultCode::ShiftMustBeOpened | null
    $e->getMessage();             // 'Смена должна быть открыта.'
    if ($e->is(ResultCode::ShiftMustBeOpened)) {
        $pos->register()->openShift('Кассир');
    }
}
```

<a id="laravel"></a>
## Laravel

Auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=virtual-pos-andoz-config
```

`.env`:

```dotenv
VIRTUAL_POS_TOKEN=your-ofd-token
# VIRTUAL_POS_AUTH_PREFIX=Bearer
# VIRTUAL_POS_BASE_URL=https://vkassa-api.ofd.tj
# VIRTUAL_POS_TIMEOUT=30
```

Use the facade or inject the client:

```php
use TexHub\VirtualPosAndoz\Laravel\VirtualPos;
use TexHub\VirtualPosAndoz\Requests\{Product, ReceiptRequest};

VirtualPos::register()->status();

VirtualPos::receipts()->create(
    ReceiptRequest::income()->addProduct(Product::make('Шкаф', 10000, 1))->cash(10000)
);
```

```php
public function __construct(private \TexHub\VirtualPosAndoz\VirtualPos $pos) {}
```

## Testing

The SDK ships with an injectable `Transport`, so you can test without the network:

```php
use TexHub\VirtualPosAndoz\Tests\Support\FakeTransport;
use TexHub\VirtualPosAndoz\{Config, VirtualPos};

$t = (new FakeTransport())->push(['fdNumber' => 3, 'fpd' => '0305354977']);
$pos = new VirtualPos(new Config(token: 'TKN'), $t);
```

```bash
composer test
```

## License

MIT © TexHub Pro — developed by Mahmudi Shodmehr.
