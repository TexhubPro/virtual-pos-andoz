# TexHub · Виртуальная касса (Andoz / ОФД)

[English](README.md) · **🌐 Русский**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

Чистый, фреймворк-независимый PHP SDK для **API виртуальных касс** (ОФД / Andoz, `vkassa-api.ofd.tj`) — состояние кассы, смены, чеки и коррекции, фискальные документы, X/ФН/кассовые отчёты, размен наличных и универсальные счётчики — с полноценной поддержкой **Laravel**.

> 📖 **Полный справочник методов** (каждый метод, параметры и примеры ответов): [`docs/REFERENCE.ru.md`](docs/REFERENCE.ru.md) · [English](docs/REFERENCE.md)

## ✨ Возможности

- 🧾 **Чеки и коррекции** через fluent-билдер (авто `receiptSum`, авто-НДС)
- 🔄 **Смены** — состояние, открытие, закрытие
- 📄 **Фискальные документы** — последний, по номеру ФД, по внешнему id
- 📊 **Отчёты** — X-отчёт, ФН-отчёт, инкассация, очередь неотправленных
- 💵 **Наличные для размена** — внесение / изъятие
- 🔢 **Универсальные счётчики** — создание, список, +/−, сброс, удаление
- 🎯 Типизированные **enum** (тип операции, СНО, предмет расчёта, НДС, коды ошибок)
- 🧪 Полностью **покрыт тестами**, без сети (внедряемый транспорт)
- ⚙️ **Laravel**: сервис-провайдер, фасад, публикуемый конфиг

## Требования

- PHP `^8.2`, `ext-curl`, `ext-json`
- Laravel 11/12/13 (опционально)

## Установка

```bash
composer require texhub/virtual-pos-andoz
```

## Быстрый старт (любой PHP-проект)

```php
use TexHub\VirtualPosAndoz\VirtualPos;
use TexHub\VirtualPosAndoz\Enums\TaxType;
use TexHub\VirtualPosAndoz\Requests\Product;
use TexHub\VirtualPosAndoz\Requests\ReceiptRequest;

$pos = VirtualPos::make('ВАШ_ТОКЕН_ОФД');

// 1) Открыть смену
$pos->register()->openShift('Бульбашев Б.Б.');

// 2) Сформировать чек прихода (наличные)
$receipt = $pos->receipts()->create(
    ReceiptRequest::income(TaxType::General)
        ->addProduct(Product::make('Шкаф', price: 10000, quantity: 1))
        ->cash(10000)
        ->consumer('sim@mail.ru')
        ->externalId('7887788778')
);

echo $receipt->get('fdNumber');   // 3
echo $receipt->get('fpd');        // 0305354977

// 3) Закрыть смену
$pos->register()->closeShift('Бульбашев Б.Б.');
```

> Все денежные значения — в **минимальных единицах валюты** (например, дирамы).
> `sum` по позиции считается автоматически (`price * quantity`), а `receiptSum` —
> это сумма всех позиций.

## Аутентификация

Токен от оператора ОФД передаётся в заголовке `Authorization`. По умолчанию
отправляется «как есть»; если оператор ждёт схему — задайте префикс:

```php
use TexHub\VirtualPosAndoz\Config;
use TexHub\VirtualPosAndoz\VirtualPos;

$pos = new VirtualPos(new Config(token: 'TOKEN', authPrefix: 'Bearer'));
```

## Чеки

```php
use TexHub\VirtualPosAndoz\Enums\{TaxType, VatCode, Commodity};
use TexHub\VirtualPosAndoz\Requests\{Product, ReceiptRequest};

$receipt = ReceiptRequest::income(TaxType::General)
    ->addProduct(
        Product::make('Гречка', price: 2500, quantity: 2, commodity: Commodity::Goods, vatCode: VatCode::Standard)
            ->codeFromRaw('4880230146327')   // маркировка/штрихкод → Base64
    )
    ->addProduct(Product::make('Доставка', price: 1000, vatCode: VatCode::Reduced1))
    ->cash(6000)
    // ->nonCash(6000, bankRRN: '123456789012', bankCard: '123456****1234')
    ->consumer('992900123456')
    ->externalId('ORD-100032');

$pos->receipts()->create($receipt);
```

**НДС** — по умолчанию SDK выделяет НДС из суммы (цена с НДС) по каждой ставке.
Чтобы задать суммы точно вручную — вызовите `->vat(VatCode::Standard, 1228)`
(отключает авто-режим) или `->withoutAutoVat()`.

### Коррекции

```php
use TexHub\VirtualPosAndoz\Requests\CorrectionReceiptRequest;

$pos->receipts()->createCorrection(
    CorrectionReceiptRequest::income(TaxType::General)
        ->self(timestamp: 1731332618, orderNumber: '123Ж-Э1')  // либо ->forced(...)
        ->addProduct(Product::make('Шкаф', 10000, 1))
        ->cash(10000)
);
```

## Документы, отчёты, наличные и счётчики

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

## Чтение ответов

Каждый вызов возвращает `Response`. `get()` читает (dot-path) внутри блока
`data`; `all()` — полный ответ, `success()` — проверка `rc === SUCCESS`.

```php
$status = $pos->register()->status();
$status->get('shiftStatus');         // bool
$status->get('exchangeBalance');     // разменный баланс
$status->get('registrationInformation.orgName');
```

## Обработка ошибок

Любой код ответа, отличный от `SUCCESS`, бросает `ApiException`:

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

Пакет подхватывается автоматически. Опубликуйте конфиг:

```bash
php artisan vendor:publish --tag=virtual-pos-andoz-config
```

`.env`:

```dotenv
VIRTUAL_POS_TOKEN=ваш-токен-офд
# VIRTUAL_POS_AUTH_PREFIX=Bearer
# VIRTUAL_POS_BASE_URL=https://vkassa-api.ofd.tj
# VIRTUAL_POS_TIMEOUT=30
```

Через фасад или внедрение клиента:

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

## Тестирование

В SDK встроен внедряемый `Transport` — тестируйте без обращения к сети:

```php
use TexHub\VirtualPosAndoz\Tests\Support\FakeTransport;
use TexHub\VirtualPosAndoz\{Config, VirtualPos};

$t = (new FakeTransport())->push(['fdNumber' => 3, 'fpd' => '0305354977']);
$pos = new VirtualPos(new Config(token: 'TKN'), $t);
```

```bash
composer test
```

## Лицензия

MIT © TexHub Pro — разработано Mahmudi Shodmehr.
