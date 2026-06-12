<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz;

use TexHub\VirtualPosAndoz\Enums\Environment;
use TexHub\VirtualPosAndoz\Exceptions\ConfigurationException;

/**
 * Immutable SDK configuration for the Virtual POS (ОФД / Andoz) API.
 *
 * Authentication uses a token issued by the ОФД operator, sent in the
 * `Authorization` HTTP header.
 */
final class Config
{
    /**
     * @param string      $token         Token issued by the ОФД operator (sent as the Authorization header).
     * @param Environment $environment   Target environment.
     * @param int         $timeout       HTTP timeout in seconds.
     * @param string|null $baseUrl       Override the environment base URL (e.g. a staging instance).
     * @param string|null $authPrefix    Optional scheme prefix for the Authorization header (e.g. "Bearer").
     */
    public function __construct(
        public readonly string $token,
        public readonly Environment $environment = Environment::Production,
        public readonly int $timeout = 30,
        private readonly ?string $baseUrl = null,
        public readonly ?string $authPrefix = null,
    ) {
        if (trim($this->token) === '') {
            throw new ConfigurationException('Virtual POS token must not be empty.');
        }

        if ($this->timeout < 1) {
            throw new ConfigurationException('Virtual POS timeout must be a positive number of seconds.');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $environment = $config['environment'] ?? Environment::Production;
        if (is_string($environment)) {
            $environment = Environment::fromString($environment);
        }

        return new self(
            token: (string) ($config['token'] ?? ''),
            environment: $environment instanceof Environment ? $environment : Environment::Production,
            timeout: (int) ($config['timeout'] ?? 30),
            baseUrl: isset($config['base_url']) && $config['base_url'] !== '' ? (string) $config['base_url'] : null,
            authPrefix: isset($config['auth_prefix']) && $config['auth_prefix'] !== '' ? (string) $config['auth_prefix'] : null,
        );
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? $this->environment->baseUrl(), '/');
    }

    public function url(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * The value of the Authorization header.
     */
    public function authorization(): string
    {
        return $this->authPrefix !== null ? $this->authPrefix . ' ' . $this->token : $this->token;
    }
}
