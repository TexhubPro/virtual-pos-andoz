<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Http;

use TexHub\VirtualPosAndoz\Exceptions\TransportException;

/**
 * Default {@see Transport} implementation built on the cURL extension.
 */
final class CurlTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly string $userAgent = 'texhub-virtual-pos-andoz/1.0 (+https://texhub.pro)',
    ) {
    }

    public function request(string $method, string $url, array $headers = [], ?array $json = null): RawResponse
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
        ];

        if ($json !== null) {
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new TransportException('Failed to JSON-encode the request body: ' . json_last_error_msg());
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
            $headers['Content-Type'] = 'application/json';
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $options[CURLOPT_HTTPHEADER] = $headerLines;

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $errorNo = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errorNo !== 0 || $body === false) {
            throw new TransportException(sprintf('Virtual POS request to %s failed: %s', $url, $error ?: 'unknown cURL error'));
        }

        return new RawResponse($statusCode, (string) $body);
    }
}
