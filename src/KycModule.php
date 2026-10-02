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
     * @return array{sessionId: string, url: string}
     */
    public function startSession(string $externalUserId): array
    {
        /** @var array{sessionId: string, url: string} */
        return $this->http->post('/partner-api/kyc/sessions', ['externalUserId' => $externalUserId]);
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
