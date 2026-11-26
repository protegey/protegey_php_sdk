<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Device events — `POST /partner-api/device-events`. Sandbox-scoped.
 *
 * This SDK does not collect device data itself; use it to relay, from your backend, what a
 * Protegey client SDK (JS / Flutter / React Native) or your own app collected.
 */
class DeviceEventsModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Records a device event. Required keys: eventId (idempotency key, unique globally — a repeat
     * is ignored, not an error), visitorId. Optional: externalCustomerId, phoneNumber (stored
     * encrypted), deviceAttributes — any of platform, osVersion, deviceModel, userAgent,
     * screenWidth, screenHeight, timezone, language, appVersion, isRooted, isEmulator,
     * connectionType, devicePixelRatio, manufacturer, cpuCores, totalMemoryMb, totalStorageMb,
     * freeStorageMb, batteryLevel, isCharging, isVpnActive (unknown keys are dropped server-side),
     * location (GPS fix of the device: { latitude, longitude, accuracyMeters?, capturedAt? (ISO
     * 8601), isMocked? } — a transaction of the same customer within ~15 min reuses it).
     *
     * Returns { recorded: true, action: 'ALLOW'|'SOFT_CHALLENGE'|'HARD_CHALLENGE'|'BLOCK',
     * riskScore: int }.
     *
     * @param array<string, mixed> $input
     * @return array{recorded: bool, action: string, riskScore: int}
     */
    public function record(array $input): array
    {
        /** @var array{recorded: bool, action: string, riskScore: int} */
        return $this->http->post('/partner-api/device-events', $input);
    }
}
