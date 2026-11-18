<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Shared fraud signal network — `POST /partner-api/shared-signal/check`.
 *
 * Requires the network to be enabled in Organization Settings for 7 consecutive days (otherwise
 * a 400). Reporting and retracting signals are portal-only.
 */
class SharedSignalModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Checks identifiers against fraud reports from other partners. Give at least one of
     * phoneNumber, email, deviceFingerprint (a device visitorId), governmentId, personIdentityId
     * (uuid). Optional externalCustomerId: on a match, your own risk profile for that customer is
     * raised.
     *
     * Returns { flagged, category, reportedDaysAgo, flagConfidence (0–100|null), matchCount,
     * matches: [{ category, reportedDaysAgo }] } — category is CONFIRMED_FRAUD | IDENTITY_THEFT |
     * MONEY_LAUNDERING | OTHER (null at the root when not flagged). The reporting partner's
     * identity is never returned.
     *
     * @param array{phoneNumber?: string, email?: string, deviceFingerprint?: string, governmentId?: string, personIdentityId?: string, externalCustomerId?: string} $input
     * @return array{flagged: bool, category: ?string, reportedDaysAgo: ?int, flagConfidence: ?int, matchCount: int, matches: list<array{category: string, reportedDaysAgo: int}>}
     */
    public function check(array $input): array
    {
        /** @var array{flagged: bool, category: ?string, reportedDaysAgo: ?int, flagConfidence: ?int, matchCount: int, matches: list<array{category: string, reportedDaysAgo: int}>} */
        return $this->http->post('/partner-api/shared-signal/check', $input);
    }
}
