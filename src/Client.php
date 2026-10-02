<?php

declare(strict_types=1);

namespace Protegey\Sdk;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin HTTP wrapper shared by every SDK module — one place that knows about auth, base URL and
 * error shape. Accepts an injectable Guzzle client so tests can use a MockHandler without a real
 * network call.
 *
 * $baseUrl is deliberately required, with NO built-in default: confirm the current value with
 * Protegey before you ship (it can differ between environments and change independently of this
 * package's version).
 */
class Client
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly ClientInterface $http;

    public function __construct(string $apiKey, string $baseUrl, ?ClientInterface $httpClient = null)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Protegey: apiKey is required');
        }
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('Protegey: baseUrl is required — point it at your Protegey API environment (e.g. https://api.protegey.com)');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $httpClient ?? new GuzzleClient();
    }

    /** @param array<string, mixed> $body */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            $response = $this->http->request($method, $this->baseUrl . $path, [
                'headers' => ['x-api-key' => $this->apiKey],
                ...($body !== null ? ['json' => $body] : []),
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new ProtegeyApiException(0, $e->getMessage());
        }

        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), true);

        if ($status < 200 || $status >= 300) {
            $rawMessage = $data['message'] ?? $response->getReasonPhrase();
            $message = is_array($rawMessage) ? implode(', ', $rawMessage) : (string) $rawMessage;
            throw new ProtegeyApiException($status, $message);
        }

        return is_array($data) ? $data : [];
    }
}
