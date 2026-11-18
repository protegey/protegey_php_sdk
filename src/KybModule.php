<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * KYB applicants (business onboarding) — `/partner-api/kyb/applicants`. Not sandbox-scoped: KYB
 * has no sandbox/live split.
 *
 * Applicant (business) `status`: INVITED, PENDING_REVIEW, MORE_INFO_REQUIRED, ACTIVE, REJECTED.
 * `submission.status`: PENDING, SUBMITTED, MORE_INFO_REQUIRED, APPROVED, REJECTED.
 * Screening `decision`: BLOCKED, REVIEW, CLEAR.
 *
 * Internal fields the API currently leaks on submissions (`tokenHash`, `documentFilePath`) are
 * removed from every response by the SDK — never rely on them.
 */
class KybModule
{
    public const DECISION_APPROVE = 'APPROVE';
    public const DECISION_REJECT = 'REJECT';
    public const DECISION_REQUEST_MORE_INFO = 'REQUEST_MORE_INFO';

    private const INTERNAL_SUBMISSION_FIELDS = ['tokenHash', 'documentFilePath'];

    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Lists applicants, newest first, each with its `submission` (or null). Accepted keys: page,
     * limit, search, status (any case), stage ('invited'|'responded').
     *
     * @param array{page?: int, limit?: int, search?: string, status?: string, stage?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function listApplicants(array $query = []): array
    {
        $result = $this->http->get('/partner-api/kyb/applicants', $query);
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_map(fn ($row) => is_array($row) ? $this->stripBusiness($row) : $row, $result['data']);
        }

        /** @var array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
        return $result;
    }

    /**
     * One applicant with its `submission` (questionnaire sections, document metadata, review and
     * screening fields).
     *
     * @return array<string, mixed>
     */
    public function getApplicant(string $clientId): array
    {
        return $this->stripBusiness($this->http->get('/partner-api/kyb/applicants/' . rawurlencode($clientId)));
    }

    /**
     * Invites a business to fill in your KYB questionnaire. At least one of $contactEmail or
     * $contactPhone is required; an invitation email is only sent when $contactEmail is given —
     * otherwise deliver the returned `applicationUrl` yourself (SMS, WhatsApp...). $templateId
     * selects a KYB form template (omit for the default one).
     *
     * Returns the applicant (status INVITED, no `submission`) plus `applicationUrl`.
     *
     * @return array<string, mixed>
     */
    public function invite(
        string $contactName,
        ?string $contactEmail = null,
        ?string $contactPhone = null,
        ?string $templateId = null,
    ): array {
        if ($contactEmail === null && $contactPhone === null) {
            throw new \InvalidArgumentException('Protegey: contactEmail or contactPhone is required');
        }

        $body = array_filter([
            'contactName' => $contactName,
            'contactEmail' => $contactEmail,
            'contactPhone' => $contactPhone,
            'templateId' => $templateId,
        ], static fn ($value) => $value !== null);

        return $this->http->post('/partner-api/kyb/applicants', $body);
    }

    /**
     * Rotates the invitation link. Only allowed while the submission is PENDING or
     * MORE_INFO_REQUIRED. Returns the **submission** (not the business) plus the new
     * `applicationUrl`.
     *
     * @return array<string, mixed>
     */
    public function resendInvitation(string $clientId): array
    {
        return $this->stripSubmission(
            $this->http->post('/partner-api/kyb/applicants/' . rawurlencode($clientId) . '/resend-invitation'),
        );
    }

    /**
     * Runs sanctions/PEP screening on the business name and each declared owner, and stores the
     * result on the submission. Returns { business, owners[], screenedAt, provider, providers,
     * defaultListChecked, degraded, fallbackReason? }.
     *
     * @return array<string, mixed>
     */
    public function screen(string $clientId): array
    {
        return $this->http->get('/partner-api/kyb/applicants/' . rawurlencode($clientId) . '/screen');
    }

    /**
     * Reviews a SUBMITTED application. $decision: 'APPROVE' | 'REJECT' | 'REQUEST_MORE_INFO'.
     * $comment is the rejection `reason` (required for REJECT, min 5 chars) or the `note` sent to
     * the applicant (required for REQUEST_MORE_INFO, min 5 chars); ignored for APPROVE.
     *
     * Returns the applicant (no `submission`); `applicationUrl` is included only for
     * REQUEST_MORE_INFO. Fires the `kyb.status_changed` webhook.
     *
     * @return array<string, mixed>
     */
    public function decide(string $clientId, string $decision, ?string $comment = null): array
    {
        $decision = strtoupper($decision);
        $body = ['decision' => $decision];

        if ($comment !== null && $decision === self::DECISION_REJECT) {
            $body['reason'] = $comment;
        } elseif ($comment !== null && $decision === self::DECISION_REQUEST_MORE_INFO) {
            $body['note'] = $comment;
        }

        return $this->http->patch('/partner-api/kyb/applicants/' . rawurlencode($clientId) . '/decide', $body);
    }

    /**
     * @param array<string, mixed> $business
     * @return array<string, mixed>
     */
    private function stripBusiness(array $business): array
    {
        if (isset($business['submission']) && is_array($business['submission'])) {
            $business['submission'] = $this->stripSubmission($business['submission']);
        }

        return $business;
    }

    /**
     * @param array<string, mixed> $submission
     * @return array<string, mixed>
     */
    private function stripSubmission(array $submission): array
    {
        return array_diff_key($submission, array_flip(self::INTERNAL_SUBMISSION_FIELDS));
    }
}
