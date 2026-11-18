<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Sanctions / PEP screening — `POST /partner-api/sanctions/screen`. Not sandbox-scoped.
 */
class SanctionsModule
{
    public const TYPE_PERSON = 'person';
    public const TYPE_BUSINESS = 'business';

    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Screens a name. $type: 'person' | 'business' (the API only accepts lowercase; the SDK
     * lowercases it for you).
     *
     * Returns { decision: 'BLOCKED'|'REVIEW'|'CLEAR', score, pepMatch, matches[], provider,
     * providers[], defaultListChecked, degraded, fallbackReason? }. Each match: id, name, type
     * (person|business, or pep|sanction for Dow Jones), source (nigsac|ofac|eu|un|au|dfat|custom|
     * dow_jones), sourceId, aliases, dateOfBirth, nationality, listingDate, notes, score,
     * matchedOn (name|alias), isPep.
     *
     * @return array<string, mixed>
     */
    public function screen(string $name, ?string $type = null): array
    {
        $body = ['name' => $name];
        if ($type !== null) {
            $body['type'] = strtolower($type);
        }

        return $this->http->post('/partner-api/sanctions/screen', $body);
    }
}
