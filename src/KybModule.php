<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * KYB applicants (business onboarding) — `/partner-api/kyb/applicants`. Not sandbox-scoped: KYB
 * has no sandbox/live split.
 *
 * Applicant (business) `status`: INVITED, PENDING_REVIEW, MORE_INFO_REQUIRED, ACTIVE, REJECTED.
 * Every applicant carries `source` ('invitation'|'import'), `externalReference` (your id, set by an
 * import, or null) and `importBatchId` (the import that created it, or null).
 * `submission.status`: PENDING, SUBMITTED, MORE_INFO_REQUIRED, APPROVED, REJECTED.
 * Screening `decision`: BLOCKED, REVIEW, CLEAR.
 *
 * Identity checks (KYC) requested for an applicant via requestKycVerification() are standard KYC
 * sessions: they follow the API key's sandbox/live mode like any KYC session (`livemode`).
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
     * limit, search, status (any case), stage ('invited'|'responded'), importId (only the applicants
     * created by that import — see importApplicants()).
     *
     * @param array{page?: int, limit?: int, search?: string, status?: string, stage?: string, importId?: string} $query
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
     * screening fields) and `kycVerifications` (identity checks requested for it, newest first —
     * same rows as listKycVerifications()).
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
     * Imports your existing KYB clients (1 to 1000) — same columns as the portal CSV template (KYB
     * page → Import). Each applicant: legalName, country (ISO 3166-1 alpha-2), contactName
     * (required); contactEmail or contactPhone (at least one); optional registrationNumber,
     * tradingName, legalStatus, incorporationDate (YYYY-MM-DD), address, website,
     * owners [{name, ownershipPercent?}], directors [{name, role?}], status ('APPROVED' — default —
     * or 'PENDING_REVIEW'), externalReference (your id, unique).
     *
     * Valid applicants are created already submitted and are never emailed. Invalid or duplicate
     * ones (same contactEmail, registrationNumber or externalReference as an existing client or
     * another row) are reported in `errors` and skipped — they never fail the call. Sanctions/PEP
     * screening then runs in the background: follow it with getImport(). $dryRun only validates.
     *
     * Returns { totalRows, importedCount, rejectedCount, errors: [{ row (0-based index in
     * $applicants), field (or null), code, error }], importId (null on a dry run or when nothing was
     * created) }. `code`: required, invalid, contact_required, unknown_field, column_count,
     * duplicate_in_file, duplicate_existing.
     *
     * @param list<array<string, mixed>> $applicants
     * @return array{totalRows: int, importedCount: int, rejectedCount: int, errors: list<array{row: int, field: string|null, code: string, error: string}>, importId: string|null}
     */
    public function importApplicants(array $applicants, bool $dryRun = false): array
    {
        if ($applicants === [] || count($applicants) > 1000) {
            throw new \InvalidArgumentException('Protegey: 1 to 1000 applicants per import');
        }

        $body = ['applicants' => array_values($applicants)];
        if ($dryRun) {
            $body['dryRun'] = true;
        }

        /** @var array{totalRows: int, importedCount: int, rejectedCount: int, errors: list<array{row: int, field: string|null, code: string, error: string}>, importId: string|null} */
        return $this->http->post('/partner-api/kyb/applicants/import', $body);
    }

    /**
     * Follows an import's background screening. Returns { importId, status ('SCREENING' then
     * 'COMPLETED'), source ('api'|'portal'), totalRows, importedCount, rejectedCount, screenedCount,
     * flaggedCount, flaggedClientIds (up to 500 — getApplicant() for the match detail), createdAt,
     * completedAt (or null) }. A flagged applicant has a sanctions/PEP match to review on the
     * business, an owner or a director — Protegey never rejects it for you.
     *
     * @return array<string, mixed>
     */
    public function getImport(string $importId): array
    {
        return $this->http->get('/partner-api/kyb/imports/' . rawurlencode($importId));
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
     * defaultListChecked, degraded, fallbackReason?, networkFlags? }. `networkFlags` (fixed
     * questionnaire only: shared-network check of the business phone / owner tax ids) is a list of
     * { label, flagged, matchCount, flagConfidence, groupEntities? } — `groupEntities` lists the
     * entities of your own partner group that reported it (the public network stays anonymous).
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
     * Asks the person behind an applicant to verify their identity — a standard KYC session linked
     * to the KYB (billed as one). Only once the applicant has submitted their application (400
     * otherwise); callable any number of times, each call creating a new session.
     *
     * All parameters are optional. $fullName (default: the applicant contact name) is compared with
     * the name read on the ID document (`nameMatch` / `kybNameMatch`) — set it to check e.g. a
     * director instead. Protegey emails the link only when $email is given; otherwise relay `url`
     * yourself. $phone defaults to the applicant contact phone. $externalUserId is echoed back on the
     * KYC session and its webhooks.
     *
     * Returns { sessionId, kybClientId, url (single use), expiresAt (your capture-link lifetime
     * setting, never less than 7 days here), emailedTo (null when no $email was given) }. Results
     * arrive on the `kyc.status_changed` webhook, with `kybClientId` and `kybNameMatch`.
     *
     * @return array{sessionId: string, kybClientId: string, url: string, expiresAt: string, emailedTo: string|null}
     */
    public function requestKycVerification(
        string $clientId,
        ?string $fullName = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $externalUserId = null,
    ): array {
        $body = array_filter([
            'fullName' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'externalUserId' => $externalUserId,
        ], static fn ($value) => $value !== null);

        /** @var array{sessionId: string, kybClientId: string, url: string, expiresAt: string, emailedTo: string|null} */
        return $this->http->post('/partner-api/kyb/applicants/' . rawurlencode($clientId) . '/kyc-sessions', $body);
    }

    /**
     * Identity checks requested for an applicant, newest first (empty `data` when none). Each row:
     * sessionId, status, outcome, recommendation, expectedName, documentFullName, nameMatch
     * ('MATCH'|'MISMATCH'|null — expectedName vs documentFullName, order/case/accents ignored, extra
     * middle names tolerated; null before a result), livenessScore, faceMatchScore, livemode,
     * createdAt, updatedAt. Use KycModule::getSession($sessionId) for images and full detail.
     *
     * @return array{data: list<array<string, mixed>>}
     */
    public function listKycVerifications(string $clientId): array
    {
        /** @var array{data: list<array<string, mixed>>} */
        return $this->http->get('/partner-api/kyb/applicants/' . rawurlencode($clientId) . '/kyc-sessions');
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
