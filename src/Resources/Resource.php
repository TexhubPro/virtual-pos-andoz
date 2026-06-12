<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Config;
use TexHub\VirtualPosAndoz\Http\ApiClient;

abstract class Resource
{
    public function __construct(
        protected readonly ApiClient $api,
        protected readonly Config $config,
    ) {
    }
}
