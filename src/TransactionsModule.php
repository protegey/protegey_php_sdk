<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Transaction monitoring — `/partner-api/transactions`. Sandbox-scoped (100 sandbox transactions
 * per UTC day).
 *
 * `decision`: CLEAR | REVIEW | BLOCKED. `deviceAction`: ALLOW | SOFT_CHALLENGE | HARD_CHALLENGE |
 * BLOCK | null.
 */
class TransactionsModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Thin, faithful mapping onto POST /partner-api/transactions — all validation and business
     * logic stays server-side.
     *
     * Required keys: externalTransactionId (idempotency key — a repeat returns 409),
     * externalCustomerId, direction ('DEBIT'|'CREDIT'), amount (positive), transactionType.
     * Optional: occurredAt (ISO 8601, defaults to now), currency (defaults to XOF server-side),
     * customerName, segment ('KYC1'|'KYC2'|'AGENT'|'SUPER_AGENT'|'MERCHANT'|'CORPORATE'), country,
     * isPep, counterpartyExternalId, isCash, channel ('branch'|'atm'|'pos'|'online'|'mobile_app'|
     * 'ussd'|'agent'|'api'|'call_center'), counterpartyInstitutionCode (SWIFT/BIC or a local
     * bank/MNO code), counterpartyCountry, lastPasswordChangeAt / lastEmailChangeAt /
     * lastPhoneChangeAt (ISO 8601 — lets a Pan Studio rule flag a large transaction shortly after a
     * credential change / SIM swap; omit and the rule field never fires), accountBalanceBefore
     * (balance right before a DEBIT — account-drain signal), visitorId (device id from a client
     * SDK; when omitted Protegey matches a recent device event for this customer), deviceAttributes
     * (see DeviceEventsModule::record()), devicePhoneNumber (the customer's own account phone
     * number, which you already know — stored encrypted).
     *
     * `direction` and `segment` must be uppercase on the wire; the SDK uppercases them for you.
     *
     * Returns { transactionId, decision, riskScore, deviceAction, alerts[] }.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function report(array $input): array
    {
        foreach (['direction', 'segment'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = strtoupper($input[$key]);
            }
        }

        return $this->http->post('/partner-api/transactions', [
            ...$input,
            'occurredAt' => $input['occurredAt'] ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Lists transactions, most recent `occurredAt` first. Accepted keys: page, limit, direction,
     * decision, externalCustomerId, dateFrom, dateTo (ISO 8601, compared against occurredAt).
     *
     * Each row: transactionId, externalTransactionId, externalCustomerId, direction, amount (a
     * decimal STRING, e.g. "15000.00"), currency, decision, riskScore, deviceAction, occurredAt,
     * createdAt.
     *
     * @param array{page?: int, limit?: int, direction?: string, decision?: string, externalCustomerId?: string, dateFrom?: string, dateTo?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(array $query = []): array
    {
        /** @var array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
        return $this->http->get('/partner-api/transactions', $query);
    }

    /**
     * One transaction by Protegey `transactionId` OR your own `externalTransactionId`. Returns
     * transactionId, externalTransactionId, externalCustomerId, decision, riskScore, deviceAction,
     * occurredAt, createdAt and up to 50 alerts (id, alertNumber, ruleCode, status,
     * decisionVerdict, triggeredAt). No amount/currency/direction here — use list() for those.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->http->get('/partner-api/transactions/' . rawurlencode($id));
    }
}
