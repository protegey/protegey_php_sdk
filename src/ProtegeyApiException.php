<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Thrown for any non-2xx response (and, with $status = 0, for a transport failure).
 *
 * - $status: the HTTP status — 400 validation / business rule, 401 missing or invalid API key,
 *   403 account gating, 404 not found, 409 duplicate transaction, 402 on-premise licence only.
 * - $message: the API's `message`; a validation error's message array is joined with ", ".
 * - $errorCode: the API's `errorCode`, whatever the status, when the body carries one — e.g. 403
 *   gating errors 'PARTNER_SUSPENDED' / 'PARTNER_CONTRACT_REQUIRED' (these only block mutating
 *   calls; GETs keep working), or a 400 'NO_3D_ENROLLMENT' from liveness->startReverification()
 *   when the customer has no KYC face on file. Null otherwise.
 * - $body: the decoded error body, as returned.
 */
class ProtegeyApiException extends \RuntimeException
{
    public const PARTNER_SUSPENDED = 'PARTNER_SUSPENDED';
    public const PARTNER_CONTRACT_REQUIRED = 'PARTNER_CONTRACT_REQUIRED';
    public const NO_3D_ENROLLMENT = 'NO_3D_ENROLLMENT';

    /** @param array<string, mixed> $body */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
    ) {
        parent::__construct($message);
    }
}
