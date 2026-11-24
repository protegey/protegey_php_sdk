<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * 3D liveness and re-verification sessions — `/partner-api/liveness/sessions`. A separate, paid
 * product, billed per check where liveness was proven (see `billableChecks` / `amount` on
 * getSession()); KYC is unaffected and stays free. Sandbox-scoped like KYC: a sandbox key only
 * sees sandbox sessions.
 *
 * Two modes:
 * - LIVENESS: a selfie-only 3D liveness check — is a live person in front of the camera?
 * - REVERIFICATION: the same liveness check, then a face match against the face of that
 *   customer's latest KYC verification — is it the same person who passed KYC?
 *
 * Statuses on the wire are UPPERCASE: PENDING, COMPLETED, EXPIRED. `matchLevel` is an int
 * (higher means a stronger match; 0 means a different person), or null until a check has run.
 */
class LivenessModule
{
    /** errorCode of the 400 returned by startReverification() when the customer has no KYC face on file. */
    public const NO_3D_ENROLLMENT = ProtegeyApiException::NO_3D_ENROLLMENT;

    public const MODE_LIVENESS = 'LIVENESS';
    public const MODE_REVERIFICATION = 'REVERIFICATION';

    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Starts a selfie-only 3D liveness session. Returns the session id and the hosted capture link
     * (`url`) — single use, valid for 30 minutes — to send to or open for your end user.
     *
     * @return array{sessionId: string, mode: string, status: string, externalUserId: string|null, url: string, captureUrl: string, expiresInMinutes: int}
     */
    public function startSession(?string $externalUserId = null): array
    {
        $body = ['mode' => self::MODE_LIVENESS];

        if ($externalUserId !== null) {
            $body['externalUserId'] = $externalUserId;
        }

        /** @var array{sessionId: string, mode: string, status: string, externalUserId: string|null, url: string, captureUrl: string, expiresInMinutes: int} */
        return $this->http->post('/partner-api/liveness/sessions', $body);
    }

    /**
     * Starts a re-verification session: a 3D liveness check, then a face match against the face
     * of $externalUserId's latest KYC verification (or of $referenceVerificationId, when given).
     * Same return shape as startSession().
     *
     * Throws a 400 ProtegeyApiException with errorCode NO_3D_ENROLLMENT when that customer has no
     * KYC face on file — run a KYC session first.
     *
     * @return array{sessionId: string, mode: string, status: string, externalUserId: string|null, url: string, captureUrl: string, expiresInMinutes: int}
     */
    public function startReverification(string $externalUserId, ?string $referenceVerificationId = null): array
    {
        $body = ['mode' => self::MODE_REVERIFICATION, 'externalUserId' => $externalUserId];

        if ($referenceVerificationId !== null) {
            $body['referenceVerificationId'] = $referenceVerificationId;
        }

        /** @var array{sessionId: string, mode: string, status: string, externalUserId: string|null, url: string, captureUrl: string, expiresInMinutes: int} */
        return $this->http->post('/partner-api/liveness/sessions', $body);
    }

    /**
     * Fetches one session. $sessionId is the value returned by startSession() /
     * startReverification().
     *
     * Returns id, sessionId, mode, status (PENDING | COMPLETED | EXPIRED), externalUserId,
     * referenceEnrollmentId, livenessProven (bool|null), matchLevel (int|null), matched
     * (bool|null, REVERIFICATION only), duplicateFaceEnrollmentIds (string[]|null, not currently
     * populated), attempts, isSandbox, completedAt, createdAt, billableChecks (int), amount
     * (decimal string, USD), currency ('USD') and checks — one row per capture attempt:
     * id, livenessProven, matchLevel, billable, unitPrice (decimal string), currency, createdAt.
     *
     * @return array<string, mixed>
     */
    public function getSession(string $sessionId): array
    {
        return $this->http->get('/partner-api/liveness/sessions/' . rawurlencode($sessionId));
    }

    /**
     * Lists sessions, newest first. Accepted keys: page, limit, externalUserId (exact match on
     * your own customer id), mode ('LIVENESS' | 'REVERIFICATION', any casing; the SDK uppercases it).
     *
     * @param array{page?: int, limit?: int, externalUserId?: string, mode?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function listSessions(array $query = []): array
    {
        if (isset($query['mode']) && is_string($query['mode'])) {
            $query['mode'] = strtoupper(trim($query['mode']));
        }

        /** @var array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
        return $this->http->get('/partner-api/liveness/sessions', $query);
    }
}
