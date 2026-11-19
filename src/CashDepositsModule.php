<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Cash deposits — `/partner-api/cash-deposits`. A cash deposit is recorded as a monitored cash
 * CREDIT, so it runs through the same rules (structuring, large cash, ...) and raises the same
 * alerts as a transaction. Sandbox-scoped like transactions.
 *
 * `decision`: CLEAR | REVIEW | BLOCKED. `channel` comes back UPPERCASE (BRANCH, ATM, POS, ONLINE,
 * MOBILE_APP, USSD, AGENT, API, CALL_CENTER) and is accepted in any casing.
 */
class CashDepositsModule
{
    private const BASE = '/partner-api/cash-deposits';

    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Records a cash deposit.
     *
     * Required keys: externalCustomerId, amount (positive number), currency (ISO 4217).
     * Optional: externalTransactionId (your reference — generated when omitted; send it to make
     * retries safe: a second call with the same value throws a 409 ProtegeyApiException),
     * customerName, depositedAt (ISO 8601, defaults to the time of the call), channel, branchId,
     * agentId, location, depositorName (who physically brought the cash), metadata (object).
     *
     * Returns { cashDepositId, externalTransactionId, decision, riskScore, alerts[] } — each alert:
     * id, alertNumber, ruleCode, status, decisionVerdict, triggeredAt.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function record(array $input): array
    {
        return $this->http->post(self::BASE, $input);
    }

    /**
     * Lists cash deposits, newest `depositedAt` first. Accepted keys (all optional): page, limit
     * (1–100), dateFrom, dateTo (ISO 8601, compared against depositedAt), externalCustomerId,
     * minAmount, maxAmount, currency, channel, decision, branchId, agentId.
     *
     * Each row: cashDepositId, externalTransactionId, externalCustomerId, customerName, amount (a
     * decimal STRING, e.g. "2500000.00"), currency, channel, branchId, agentId, location,
     * depositorName, metadata, decision, riskScore, depositedAt, createdAt.
     *
     * @param array{page?: int, limit?: int, dateFrom?: string, dateTo?: string, externalCustomerId?: string, minAmount?: int|float, maxAmount?: int|float, currency?: string, channel?: string, decision?: string, branchId?: string, agentId?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(array $query = []): array
    {
        /** @var array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
        return $this->http->get(self::BASE, $query);
    }

    /**
     * One cash deposit by Protegey `cashDepositId` OR your own `externalTransactionId` — same
     * fields as a list() row, plus up to 50 `alerts`. Throws a 404 ProtegeyApiException when not
     * found.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->http->get(self::BASE . '/' . rawurlencode($id));
    }
}
