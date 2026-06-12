<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Enums;

/**
 * Virtual POS (ОФД / Andoz) API environments.
 *
 * The protocol documents a single production base URL; use a custom `base_url`
 * in the config to point at a staging instance when the operator provides one.
 */
enum Environment: string
{
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://vkassa-api.ofd.tj',
        };
    }

    public static function fromString(string $value): self
    {
        return self::Production;
    }
}
