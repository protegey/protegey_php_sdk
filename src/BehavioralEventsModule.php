<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Behavioral biometrics events — `POST /partner-api/behavioral-events`. Sandbox-scoped.
 *
 * This SDK does not capture behavior itself; use it to relay, from your backend, the aggregates a
 * client SDK or your own app measured.
 */
class BehavioralEventsModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Required keys: externalCustomerId, sessionId. Optional groups:
     * - keystroke: { avgHoldTimeMs?, avgInterKeyLatencyMs?, typingSpeedCharsPerSec?, errorRate? }
     * - touch: { avgTapPressure?, avgSwipeVelocity?, scrollBehaviorScore?, mouseAcceleration? }
     * - navigation: { screenSequence?: string[] }
     * - session: { loginHourBucket? (0–23), loginDayOfWeek? (0–6), sessionDurationMs? }
     *
     * Returns { status: 'LEARNING'|'SCORED', deviationScore, confidenceTier: 'LOW'|'MEDIUM'|'HIGH'|
     * null, stepUpRecommended, escalatedAlertId (uuid|null) }. While LEARNING, deviationScore is
     * 0 and confidenceTier null.
     *
     * @param array<string, mixed> $input
     * @return array{status: string, deviationScore: int|float, confidenceTier: ?string, stepUpRecommended: bool, escalatedAlertId: ?string}
     */
    public function record(array $input): array
    {
        /** @var array{status: string, deviationScore: int|float, confidenceTier: ?string, stepUpRecommended: bool, escalatedAlertId: ?string} */
        return $this->http->post('/partner-api/behavioral-events', $input);
    }
}
