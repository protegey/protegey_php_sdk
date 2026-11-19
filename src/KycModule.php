<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * KYC identity-verification sessions — `/partner-api/kyc/sessions`. Sandbox-scoped: a sandbox key
 * only sees sandbox sessions (and is limited to 10 new sessions per UTC day).
 *
 * Statuses on the wire are UPPER_SNAKE_CASE: NOT_STARTED, IN_PROGRESS, AWAITING_USER, IN_REVIEW,
 * APPROVED, DECLINED, RESUBMITTED, ABANDONED, EXPIRED, KYC_EXPIRED. `outcome` is SUCCESS, MANUAL,
 * FAILED or null. `recommendation` is Protegey's own verdict — APPROVE, REVIEW, REJECT or null —
 * and is never changed by a manual override: `status` is the final decision.
 *
 * Photos come as pre-signed links in an `images` block — documentFront, documentBack, selfie,
 * documentFaceCrop, each `['url' => ..., 'expiresAt' => ISO 8601]` or null. The links are valid for
 * 1 hour and re-issued on every GET, so fetch the session again rather than storing a URL.
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
     * Lists sessions, newest first. Accepted keys: page, limit (1–100), status, search (partial,
     * case-insensitive match on fullName or externalUserId), externalUserId (exact, case-sensitive
     * match on your own customer id).
     *
     * `status` may be given in the same UPPER_SNAKE_CASE you receive (e.g. 'IN_REVIEW') — the SDK
     * converts it to the Title Case value this filter requires ('In Review').
     *
     * Each row: sessionId, externalUserId, status, outcome, recommendation, fullName,
     * documentType, country, createdAt, images (pre-signed links, see the class doc — no base64).
     *
     * @param array{page?: int, limit?: int, status?: string, search?: string, externalUserId?: string} $query
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
     * Sessions for one of your customers — shorthand for listSessions(['externalUserId' => ...]).
     *
     * @param array{page?: int, limit?: int, status?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function findByExternalUserId(string $externalUserId, array $query = []): array
    {
        return $this->listSessions([...$query, 'externalUserId' => $externalUserId]);
    }

    /**
     * Fetches one session — a polling fallback for the `kyc.status_changed` webhook, whose
     * `sessionId` is this same id. $sessionId is the value returned by startSession().
     *
     * Returns sessionId, externalUserId, status, outcome, recommendation, decision (object|null),
     * fullName, documentType, country, livenessScore, faceMatchScore, images (pre-signed links,
     * valid 1 h — see the class doc), and, for backward compatibility, documentPhoto /
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
     * Returns sessionId, externalUserId, status, outcome, recommendation, decision. A status
     * override is recorded in decision.manualReview (reviewedBy 'Partner API', reviewedAt,
     * previousStatus / newStatus in UPPER_SNAKE_CASE, note = reviewNote) and fires the
     * kyc.status_changed webhook, like an automatic decision. `recommendation` is left unchanged.
     *
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function updateSession(string $sessionId, array $input): array
    {
        return $this->http->patch('/partner-api/kyc/sessions/' . rawurlencode($sessionId), $input);
    }

    /**
     * Sets the final decision on a session — shorthand for updateSession() with `status` and an
     * optional `reviewNote` (max 1000 chars, kept in decision.manualReview.note for the audit
     * trail). $status: 'APPROVED' | 'DECLINED' | 'IN_REVIEW' (any casing; the SDK uppercases it).
     *
     * @return array<string, mixed>
     */
    public function setStatus(string $sessionId, string $status, ?string $reviewNote = null): array
    {
        $input = ['status' => strtoupper(str_replace(' ', '_', trim($status)))];
        if ($reviewNote !== null) {
            $input['reviewNote'] = $reviewNote;
        }

        return $this->updateSession($sessionId, $input);
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
