<?php

declare(strict_types=1);

namespace Protegey\Sdk;

/**
 * Monitoring alerts — `/partner-api/alerts`.
 *
 * On the wire: `status` OPEN | CONFIRMED | MORE_INFO_REQUESTED | DISMISSED; `disposition`
 * CONFIRMED_FRAUD | FALSE_POSITIVE | NO_ACTION | SAR_FILED | ESCALATED | null; `decisionVerdict`
 * BLOCK | STEP_UP | ESCALATE | ALERT | null; `ruleSeverity` stays lowercase (review | block |
 * step_up | escalate).
 *
 * Every alert is returned with context: the alert fields plus ruleName, ruleNameFr,
 * ruleExplanation, ruleExplanationFr, ruleSeverity, ruleNumber and `transaction` ({ amount
 * (decimal string), currency, direction, transactionType, occurredAt } or null).
 *
 * Note: list() and get() are sandbox-scoped, but update(), convertToCase() and flagTransaction()
 * act on any of your alerts/transactions by id, whichever key you use.
 */
class AlertsModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Lists alerts, most recently triggered first. Accepted keys: page, limit, status,
     * disposition, alertNumber, ruleCode, externalCustomerId, transactionId, dateFrom, dateTo
     * (ISO 8601, compared against triggeredAt).
     *
     * The `status` and `disposition` filters only accept lowercase values server-side; pass them in
     * either case — the SDK lowercases them.
     *
     * @param array{page?: int, limit?: int, status?: string, disposition?: string, alertNumber?: int, ruleCode?: string, externalCustomerId?: string, transactionId?: string, dateFrom?: string, dateTo?: string} $query
     * @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(array $query = []): array
    {
        foreach (['status', 'disposition'] as $key) {
            if (isset($query[$key]) && is_string($query[$key])) {
                $query[$key] = strtolower($query[$key]);
            }
        }

        /** @var array{data: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
        return $this->http->get('/partner-api/alerts', $query);
    }

    /** @return array<string, mixed> */
    public function get(string $alertId): array
    {
        return $this->http->get('/partner-api/alerts/' . rawurlencode($alertId));
    }

    /**
     * Updates an alert. Accepted keys (all optional): status, disposition (or null),
     * assignedToUserId (uuid of one of your users, or null), investigationNotes (or null), dueAt
     * (ISO date, or null). Uppercase or lowercase status/disposition values are both accepted.
     *
     * Setting status CONFIRMED/DISMISSED or any disposition stamps resolvedAt. Always fires the
     * `alert.status_changed` webhook.
     *
     * @param array{status?: string, disposition?: string|null, assignedToUserId?: string|null, investigationNotes?: string|null, dueAt?: string|null} $input
     * @return array<string, mixed>
     */
    public function update(string $alertId, array $input): array
    {
        return $this->http->patch('/partner-api/alerts/' . rawurlencode($alertId), $input);
    }

    /**
     * Opens a case from this alert and sets its disposition to ESCALATED. Returns
     * { alert, case } — case status OPEN | INVESTIGATING | CLOSED, outcome NO_ACTION |
     * FALSE_POSITIVE | SAR_FILED | null, priority lowercase (critical | high | medium | low).
     *
     * @return array{alert: array<string, mixed>, case: array<string, mixed>}
     */
    public function convertToCase(string $alertId): array
    {
        /** @var array{alert: array<string, mixed>, case: array<string, mixed>} */
        return $this->http->post('/partner-api/alerts/' . rawurlencode($alertId) . '/convert-to-case');
    }

    /**
     * Manually flags a transaction (rule MANUAL-FLAG-001). $transactionId must be the Protegey
     * transaction uuid, not your externalTransactionId. Idempotent: an existing OPEN alert for the
     * transaction is returned instead of a new one.
     *
     * @return array<string, mixed>
     */
    public function flagTransaction(string $transactionId): array
    {
        return $this->http->post('/partner-api/alerts/flag-transaction', ['transactionId' => $transactionId]);
    }
}
