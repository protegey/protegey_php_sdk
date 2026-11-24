<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Protegey\Sdk\WebhookVerifier;

class WebhookVerifierTest extends TestCase
{
    private const SECRET = 'test_webhook_secret_not_real';
    private const PAYLOAD = '{"type":"alert.status_changed","eventId":"e1","alertId":"a1","ruleCode":"VEL-001","externalCustomerId":"cust-1","status":"CONFIRMED"}';

    private static function sign(string $timestamp, string $payload, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    public function testAcceptsACorrectlySignedPayload(): void
    {
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, '1700000000', self::sign('1700000000', self::PAYLOAD), self::SECRET));
    }

    public function testRejectsATamperedPayload(): void
    {
        $signature = self::sign('1700000000', self::PAYLOAD);

        $this->assertFalse(WebhookVerifier::verify(str_replace('CONFIRMED', 'DISMISSED', self::PAYLOAD), '1700000000', $signature, self::SECRET));
    }

    public function testRejectsATamperedTimestamp(): void
    {
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '1700000001', self::sign('1700000000', self::PAYLOAD), self::SECRET));
    }

    public function testRejectsAWrongSecret(): void
    {
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '1700000000', self::sign('1700000000', self::PAYLOAD, 'other'), self::SECRET));
    }

    public function testRejectsEmptyHeaders(): void
    {
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '', '', self::SECRET));
    }

    public function testOptionalToleranceRejectsStaleTimestamps(): void
    {
        $now = (string) time();
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $now, self::sign($now, self::PAYLOAD), self::SECRET, 300));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '1700000000', self::sign('1700000000', self::PAYLOAD), self::SECRET, 300));
    }
}
