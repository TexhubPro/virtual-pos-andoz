<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel facade for the Virtual POS client.
 *
 * @method static \TexHub\VirtualPosAndoz\Resources\CashRegister register()
 * @method static \TexHub\VirtualPosAndoz\Resources\Receipts     receipts()
 * @method static \TexHub\VirtualPosAndoz\Resources\Documents    documents()
 * @method static \TexHub\VirtualPosAndoz\Resources\Reports      reports()
 * @method static \TexHub\VirtualPosAndoz\Resources\Cash         cash()
 * @method static \TexHub\VirtualPosAndoz\Resources\Counters     counters()
 * @method static \TexHub\VirtualPosAndoz\Http\ApiClient         api()
 * @method static \TexHub\VirtualPosAndoz\Config                 config()
 *
 * @see \TexHub\VirtualPosAndoz\VirtualPos
 */
class VirtualPos extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'virtual-pos-andoz';
    }
}
