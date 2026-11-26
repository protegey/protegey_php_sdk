<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Verifies the HMAC-SHA256 signature Protegey attaches to every outbound webhook delivery, so you
 * can trust that a request claiming to be from Protegey actually is.
 *
 * Protegey signs `"{timestamp}.{rawBody}"` with your webhook secret (HMAC-SHA256, hex digest) and
 * sends it as the `X-Signature` header, alongside the same unix-seconds timestamp as
 * `X-Timestamp`. Pass the *raw* request body — not a re-encoded/re-serialized version of it,
 * which can produce a different byte sequence and always fail to match.
 *
 * Event types: kyc.status_changed, kyb.status_changed, alert.created, alert.status_changed,
 * transaction.blocked, webhook.test. Deliveries are retried (up to 5 attempts over ~3 hours) and
 * carry no idempotency header — deduplicate on the payload's `eventId`.
 *
 * `kyc.status_changed` = { type, eventId, externalUserId, status, recommendation, sessionId,
 * kybClientId, kybNameMatch, decision } — fired on an automatic result AND on a manual status
 * change (dashboard or KycModule::updateSession()); `sessionId` is the same id startSession()
 * returned. `kybClientId` / `kybNameMatch` ('MATCH'|'MISMATCH') are set only for a session
 * requested via KybModule::requestKycVerification(), null otherwise.
 * `kyb.status_changed` = { type, eventId, clientId, status, submissionStatus, decision }. Every
 * status-like value (status, submissionStatus, decision, decision.manualReview.previousStatus /
 * newStatus, recommendation) is UPPER_SNAKE_CASE.
 */
final class WebhookVerifier
{
    public const SIGNATURE_HEADER = 'X-Signature';
    public const TIMESTAMP_HEADER = 'X-Timestamp';

    public const EVENT_KYC_STATUS_CHANGED = 'kyc.status_changed';
    public const EVENT_KYB_STATUS_CHANGED = 'kyb.status_changed';
    public const EVENT_ALERT_CREATED = 'alert.created';
    public const EVENT_ALERT_STATUS_CHANGED = 'alert.status_changed';
    public const EVENT_TRANSACTION_BLOCKED = 'transaction.blocked';
    public const EVENT_WEBHOOK_TEST = 'webhook.test';

    /**
     * Constant-time check of $signature against HMAC-SHA256(secret, "{timestamp}.{payload}").
     *
     * $toleranceSeconds (optional) additionally rejects a delivery whose X-Timestamp is further
     * than that from now — a replay guard. Leave it null to skip the check; if you enable it, keep
     * it generous, since retries are re-signed with a fresh timestamp but clocks can drift.
     */
    public static function verify(
        string $payload,
        string $timestamp,
        string $signature,
        string $secret,
        ?int $toleranceSeconds = null,
    ): bool {
        if ($timestamp === '' || $signature === '' || $secret === '') {
            return false;
        }

        if ($toleranceSeconds !== null) {
            if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $toleranceSeconds) {
                return false;
            }
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }
}
