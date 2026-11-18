<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * KYC identity-verification sessions — `/partner-api/kyc/sessions`. Sandbox-scoped: a sandbox key
 * only sees sandbox sessions (and is limited to 10 new sessions per UTC day).
 *
 * Statuses on the wire are UPPER_SNAKE_CASE: NOT_STARTED, IN_PROGRESS, AWAITING_USER, IN_REVIEW,
 * APPROVED, DECLINED, RESUBMITTED, ABANDONED, EXPIRED, KYC_EXPIRED. `outcome` is SUCCESS, MANUAL,
 * FAILED or null.
 */
class KycModule
{
    /** The list endpoint's `status` filter only accepts the internal Title Case values. */
    private const LIST_STATUS_FILTER = [
        'NOT_STARTED' => 'Not Started',
        'IN_PROGRESS' => 'In Progress',
        'AWAITING_USER' => 'Awaiting User',
        'IN_REVIEW' => 'In Review',
        'APPROVED' => 'Approved',
        'DECLINED' => 'Declined',
        'RESUBMITTED' => 'Resubmitted',
        'ABANDONED' => 'Abandoned',
        'EXPIRED' => 'Expired',
        'KYC_EXPIRED' => 'Kyc Expired',
    ];

    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Starts an identity verification session for one of your end users. Returns the Protegey
     * session id and the hosted capture link — single use, valid for 30 minutes.
     *
     * $email and $phone are optional and never verified by Protegey — same self-declared model as
     * Pan Studio's other fields. They feed cross-partner identity-resolution matching, so pass
     * them when you have them even though they're not required.
     *
     * @return array{sessionId: string, url: string}
     */
    public function startSession(string $externalUserId, ?string $email = null, ?string $phone = null): array
    {
        $body = ['externalUserId' => $externalUserId];

        if ($email !== null) {
            $body['email'] = $email;
        }

        if ($phone !== null) {
            $body['phone'] = $phone;
        }

        /** @var array{sessionId: string, url: string} */
        return $this->http->post('/partner-api/kyc/sessions', $body);
    }

    /**
     * Lists sessions, newest first. Accepted keys: page, limit (1–100), status, search (matches
     * fullName or externalUserId).
     *
     * `status` may be given in the same UPPER_SNAKE_CASE you receive (e.g. 'IN_REVIEW') — the SDK
     * converts it to the Title Case value this filter requires ('In Review').
     *
     * Each row: sessionId, externalUserId, status, outcome, fullName, documentType, country,
     * createdAt (no photos).
     *
     * @param array{page?: int, limit?: int, status?: string, search?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function listSessions(array $query = []): array
    {
        if (isset($query['status']) && is_string($query['status'])) {
            $key = strtoupper(str_replace(' ', '_', trim($query['status'])));
            $query['status'] = self::LIST_STATUS_FILTER[$key] ?? $query['status'];
        }

        /** @var array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
        return $this->http->get('/partner-api/kyc/sessions', $query);
    }

    /**
     * Fetches one session — use it as a polling fallback for the `kyc.status_changed` webhook (that
     * webhook carries no usable session id, only `externalUserId`). $sessionId is the value
     * returned by startSession().
     *
     * Returns sessionId, externalUserId, status, outcome, decision (object|null), fullName,
     * documentType, country, livenessScore, faceMatchScore, and documentPhoto /
     * documentBackPhoto / selfiePhoto / documentFaceCrop (base64 without a data: prefix, or null —
     * photos may arrive a couple of minutes after the decision).
     *
     * @return array<string, mixed>
     */
    public function getSession(string $sessionId): array
    {
        return $this->http->get('/partner-api/kyc/sessions/' . rawurlencode($sessionId));
    }

    /**
     * Corrects extracted fields and/or overrides the status. Accepted keys (all optional):
     * fullName, externalUserId, country (ISO alpha-2), documentType, idDocumentNumber, sex,
     * dateOfBirth, placeOfBirth, profession, dateOfIssue, dateOfExpiration,
     * status ('APPROVED'|'DECLINED'|'IN_REVIEW'), reviewNote (max 1000 chars).
     *
     * Returns sessionId, externalUserId, status, outcome, decision. A status override is recorded
     * in decision.manualReview and does NOT fire the kyc.status_changed webhook.
     *
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function updateSession(string $sessionId, array $input): array
    {
        return $this->http->patch('/partner-api/kyc/sessions/' . rawurlencode($sessionId), $input);
    }

    /**
     * Issues a fresh 30-minute capture link for an existing session and moves it to RESUBMITTED.
     * Does not count against the sandbox daily quota.
     *
     * @return array{sessionId: string, url: string}
     */
    public function resendSession(string $sessionId): array
    {
        /** @var array{sessionId: string, url: string} */
        return $this->http->post('/partner-api/kyc/sessions/' . rawurlencode($sessionId) . '/resend');
    }
}
