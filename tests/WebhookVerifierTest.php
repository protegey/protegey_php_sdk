<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Protegey\Sdk\WebhookVerifier;

class WebhookVerifierTest extends TestCase
{
    public function testAcceptsACorrectlySignedPayload(): void
    {
        $secret = 'whsec_test123';
        $timestamp = '1700000000';
        $payload = '{"event":"kyc.session.updated"}';
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        $this->assertTrue(WebhookVerifier::verify($payload, $timestamp, $signature, $secret));
    }

    public function testRejectsATamperedPayload(): void
    {
        $secret = 'whsec_test123';
        $timestamp = '1700000000';
        $signature = hash_hmac('sha256', "{$timestamp}.{\"event\":\"original\"}", $secret);

        $this->assertFalse(WebhookVerifier::verify('{"event":"tampered"}', $timestamp, $signature, $secret));
    }

    public function testRejectsAWrongSecret(): void
    {
        $timestamp = '1700000000';
        $payload = '{"event":"kyc.session.updated"}';
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_correct');

        $this->assertFalse(WebhookVerifier::verify($payload, $timestamp, $signature, 'whsec_wrong'));
    }
}
