<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Verifies the HMAC-SHA256 signature Protegey attaches to every outbound webhook delivery, so you
 * can trust that a request claiming to be from Protegey actually is.
 *
 * Protegey signs `"{timestamp}.{rawBody}"` with your webhook secret (HMAC-SHA256, hex digest) and
 * sends it as the `X-Signature` header, alongside the same `timestamp` as `X-Timestamp`. Pass the
 * *raw* request body — not a re-encoded/re-serialized version of it, which can produce a different
 * byte sequence and always fail to match.
 */
final class WebhookVerifier
{
    public static function verify(string $payload, string $timestamp, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $signature);
    }
}
