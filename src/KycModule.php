<?php

declare(strict_types=1);

namespace Protegey\Sdk;

class KycModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Starts an identity verification session for one of your end users — no manual API call
     * needed. Returns ['sessionId' => string, 'url' => string] — the hosted verification link.
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
     * Polling fallback for the webhook — call this if you're not sure a webhook delivery ever
     * arrived (best-effort: one retry, no queue). $sessionId is the value returned by
     * startSession().
     *
     * @return array<string, mixed>
     */
    public function getSession(string $sessionId): array
    {
        return $this->http->get('/partner-api/kyc/sessions/' . rawurlencode($sessionId));
    }
}
