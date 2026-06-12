<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Http;

use TexHub\VirtualPosAndoz\Config;
use TexHub\VirtualPosAndoz\Enums\ResultCode;
use TexHub\VirtualPosAndoz\Exceptions\ApiException;
use TexHub\VirtualPosAndoz\Exceptions\VirtualPosException;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Talks to the Virtual POS REST API.
 *
 * Every command is a POST to `{baseURL}/api/terminal/{command}` with a JSON body
 * that always starts with `formCode`. The token is sent in the `Authorization`
 * header. Responses are `{ "rc": "...", "data": {...} }`; a non-SUCCESS `rc`
 * (or a non-2xx status) is converted into an {@see ApiException}.
 */
final class ApiClient
{
    public function __construct(
        private readonly Config $config,
        private readonly Transport $transport,
    ) {
    }

    /**
     * Execute an API command.
     *
     * @param string               $command  Endpoint segment, e.g. "deviceStatus".
     * @param string               $formCode Form code, e.g. "DEVICE_STATUS".
     * @param array<string, mixed> $params   Additional body parameters.
     */
    public function call(string $command, string $formCode, array $params = []): Response
    {
        $body = array_merge(['formCode' => $formCode], $params);

        $decoded = $this->post('api/terminal/' . $command, $body);

        return Response::fromResponse($decoded);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        $raw = $this->transport->request('POST', $this->config->url($path), $this->headers(), json: $body);

        return $this->decode($raw);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => $this->config->authorization(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(RawResponse $response): array
    {
        $decoded = $response->body === '' ? [] : json_decode($response->body, true);

        if (! is_array($decoded)) {
            if ($response->isSuccessful()) {
                throw new VirtualPosException('Unexpected non-JSON response from Virtual POS: ' . substr($response->body, 0, 200));
            }

            throw new ApiException(
                'Virtual POS API error (HTTP ' . $response->statusCode . ').',
                ResultCode::UnknownError->value,
                $response->statusCode,
            );
        }

        $rc = (string) ($decoded['rc'] ?? '');
        $failed = ! $response->isSuccessful() || $rc !== ResultCode::Success->value;

        if ($failed) {
            throw ApiException::fromResponse($response->statusCode, $decoded);
        }

        return $decoded;
    }
}
