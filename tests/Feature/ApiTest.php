<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\VirtualPosAndoz\Config;
use TexHub\VirtualPosAndoz\Enums\ResultCode;
use TexHub\VirtualPosAndoz\Enums\TaxType;
use TexHub\VirtualPosAndoz\Enums\VatCode;
use TexHub\VirtualPosAndoz\Exceptions\ApiException;
use TexHub\VirtualPosAndoz\Requests\CorrectionReceiptRequest;
use TexHub\VirtualPosAndoz\Requests\Product;
use TexHub\VirtualPosAndoz\Requests\ReceiptRequest;
use TexHub\VirtualPosAndoz\Tests\Support\FakeTransport;
use TexHub\VirtualPosAndoz\VirtualPos;

final class ApiTest extends TestCase
{
    private function pos(FakeTransport $t): VirtualPos
    {
        return new VirtualPos(new Config(token: 'TKN'), $t);
    }

    public function test_status_sends_authorization_and_correct_endpoint(): void
    {
        $t = (new FakeTransport())->push(['shiftStatus' => false, 'serialNumber' => 'VP01']);

        $status = $this->pos($t)->register()->status();

        $this->assertTrue($status->success());
        $this->assertSame('VP01', $status->get('serialNumber'));
        $this->assertFalse($status->get('shiftStatus'));
        $this->assertSame('TKN', $t->last()['headers']['Authorization']);
        $this->assertStringEndsWith('/api/terminal/deviceStatus', $t->lastUrl());
        $this->assertSame('DEVICE_STATUS', $t->lastBody()['formCode']);
    }

    public function test_auth_prefix_is_applied(): void
    {
        $t = (new FakeTransport())->push();
        (new VirtualPos(new Config(token: 'TKN', authPrefix: 'Bearer'), $t))->register()->status();

        $this->assertSame('Bearer TKN', $t->last()['headers']['Authorization']);
    }

    public function test_open_shift_body(): void
    {
        $t = (new FakeTransport())->push(['fdNumber' => 6, 'fpd' => '2810933523', 'onlineStatus' => true]);

        $res = $this->pos($t)->register()->openShift('Бульбашев Б.Б.', '5665566556');

        $this->assertSame(6, $res->get('fdNumber'));
        $body = $t->lastBody();
        $this->assertSame('OPEN_SHIFT', $body['formCode']);
        $this->assertSame('Бульбашев Б.Б.', $body['cashier']);
        $this->assertSame('5665566556', $body['externalId']);
        $this->assertStringEndsWith('/api/terminal/openShift', $t->lastUrl());
    }

    public function test_receipt_builds_sum_and_auto_vat(): void
    {
        $t = (new FakeTransport())->push(['fdNumber' => 3, 'fpd' => '0305354977', 'receiptNumber' => 1]);

        $res = $this->pos($t)->receipts()->create(
            ReceiptRequest::income(TaxType::General)
                ->addProduct(Product::make('Шкаф', 10000, 1))
                ->cash(10000)
                ->consumer('sim@mail.ru')
                ->externalId('7887788778')
        );

        $this->assertSame('0305354977', $res->get('fpd'));

        $body = $t->lastBody();
        $this->assertSame('RECEIPT', $body['formCode']);
        $this->assertSame('INCOME', $body['operationType']);
        $this->assertSame('GENERAL', $body['taxType']);
        $this->assertSame(10000, $body['receiptSum']);
        $this->assertSame(10000, $body['receiptCash']);
        $this->assertSame(0, $body['receiptNonCash']);
        $this->assertSame('sim@mail.ru', $body['consumerContacts']);
        $this->assertSame('7887788778', $body['externalId']);
        $this->assertSame(10000, $body['products'][0]['sum']);
        $this->assertSame('STANDARD', $body['products'][0]['vatCode']);
        // auto VAT: inclusive 15% of 10000 = round(10000 * 15 / 115) = 1304
        $this->assertSame('STANDARD', $body['taxes']['vats'][0]['vatCode']);
        $this->assertSame(1304, $body['taxes']['vats'][0]['vatSum']);
    }

    public function test_receipt_with_explicit_vat_and_noncash(): void
    {
        $t = (new FakeTransport())->push();

        $this->pos($t)->receipts()->create(
            ReceiptRequest::income()
                ->addProduct(Product::make('Услуга', 5000, 1, vatCode: VatCode::ZeroTax))
                ->nonCash(5000, '123456789012', '123456****1234')
                ->vat(VatCode::ZeroTax, 0)
        );

        $body = $t->lastBody();
        $this->assertSame(0, $body['receiptCash']);
        $this->assertSame(5000, $body['receiptNonCash']);
        $this->assertSame('123456789012', $body['bankRRN']);
        $this->assertSame([['vatCode' => 'ZERO_TAX', 'vatSum' => 0]], $body['taxes']['vats']);
    }

    public function test_product_code_is_base64_encoded(): void
    {
        $t = (new FakeTransport())->push();
        $this->pos($t)->receipts()->create(
            ReceiptRequest::income()->addProduct(Product::make('X', 100, 1)->codeFromRaw('ABC'))->cash(100)
        );

        $this->assertSame(base64_encode('ABC'), $t->lastBody()['products'][0]['code']);
    }

    public function test_correction_requires_reason(): void
    {
        $this->expectException(\TexHub\VirtualPosAndoz\Exceptions\ConfigurationException::class);

        CorrectionReceiptRequest::income()
            ->addProduct(Product::make('Шкаф', 10000, 1))
            ->cash(10000)
            ->toArray();
    }

    public function test_correction_body(): void
    {
        $t = (new FakeTransport())->push(['fdNumber' => 22, 'fpd' => '0282570474']);

        $this->pos($t)->receipts()->createCorrection(
            CorrectionReceiptRequest::income(TaxType::General)
                ->self(1731332618, '123Ж-Э1')
                ->addProduct(Product::make('Шкаф', 10000, 1))
                ->cash(10000)
                ->vat(VatCode::Standard, 10)
        );

        $body = $t->lastBody();
        $this->assertSame('CORRECTION_RECEIPT', $body['formCode']);
        $this->assertSame('SELF', $body['correctionType']);
        $this->assertSame(1731332618, $body['correctionReason']['timestamp']);
        $this->assertSame('123Ж-Э1', $body['correctionReason']['orderNumber']);
        $this->assertStringEndsWith('/api/terminal/formCorrectionReceipt', $t->lastUrl());
    }

    public function test_documents_and_reports_and_cash(): void
    {
        $t = (new FakeTransport())
            ->push(['fdNumber' => 23])
            ->push(['fdNumber' => 9])
            ->push(['formCode' => 'OPEN_SHIFT', 'externalId' => '5665566556'])
            ->push(['shiftNumber' => 6])
            ->push()  // addCash
            ->push(); // removeCash

        $pos = $this->pos($t);

        $this->assertSame(23, $pos->documents()->last()->get('fdNumber'));
        $this->assertStringEndsWith('/api/terminal/getLastFD', $t->lastUrl());

        $this->assertSame(9, $pos->documents()->byNumber(9)->get('fdNumber'));
        $this->assertSame(9, $t->lastBody()['fdNumber']);

        $pos->documents()->byExternalId('5665566556');
        $this->assertSame('5665566556', $t->lastBody()['externalId']);

        $pos->reports()->xReport();
        $this->assertStringEndsWith('/api/terminal/getXReport', $t->lastUrl());

        $pos->cash()->add(10000);
        $this->assertSame(10000, $t->lastBody()['addAmount']);

        $pos->cash()->remove(5000);
        $this->assertSame(5000, $t->lastBody()['removeAmount']);
    }

    public function test_counters(): void
    {
        $t = (new FakeTransport())->push()->push(['value' => []])->push()->push();
        $pos = $this->pos($t);

        $pos->counters()->create(101, 'Отмененные операции', start: 0);
        $body = $t->lastBody();
        $this->assertSame('CREATE_COUNTER', $body['formCode']);
        $this->assertSame(101, $body['counterCode']);
        $this->assertSame('LONG', $body['valueType']);

        $pos->counters()->increase(101, 3);
        $this->assertSame(3, $t->lastBody()['value']);

        $pos->counters()->reset(101);
        $this->assertSame(101, $t->lastBody()['counterCode']);
        $this->assertStringEndsWith('/api/terminal/resetCounter', $t->lastUrl());
    }

    public function test_error_result_code_raises_api_exception(): void
    {
        $t = (new FakeTransport())->pushError(ResultCode::ShiftMustBeOpened->value);

        try {
            $this->pos($t)->receipts()->create(
                ReceiptRequest::income()->addProduct(Product::make('X', 100, 1))->cash(100)
            );
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertTrue($e->is(ResultCode::ShiftMustBeOpened));
            $this->assertSame(ResultCode::ShiftMustBeOpened, $e->code());
            $this->assertSame('Смена должна быть открыта.', $e->getMessage());
        }
    }

    public function test_unknown_error_code_is_described(): void
    {
        $t = (new FakeTransport())->pushError('SOME_NEW_CODE');

        try {
            $this->pos($t)->register()->status();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('SOME_NEW_CODE', $e->rc);
            $this->assertNull($e->code());
            $this->assertStringContainsString('SOME_NEW_CODE', $e->getMessage());
        }
    }
}
