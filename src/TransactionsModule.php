<?php

declare(strict_types=1);

namespace Protegey\Sdk;

class TransactionsModule
{
    public function __construct(private readonly Client $http)
    {
    }

    /**
     * Thin, faithful mapping onto POST /partner-api/transactions — all validation and business
     * logic stays server-side.
     *
     * Required keys: externalTransactionId, externalCustomerId, direction ('DEBIT'|'CREDIT'),
     * amount, transactionType. Optional: currency, counterpartyExternalId, isCash, occurredAt
     * (defaults to now), segment, country, isPep, channel ('branch'|'atm'|'pos'|'online'|
     * 'mobile_app'|'ussd'|'agent'|'api'|'call_center'), counterpartyInstitutionCode (SWIFT/BIC
     * or a local bank/MNO code), counterpartyCountry (compare against the customer's own
     * `country` in a Pan Studio rule to flag cross-border activity).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function report(array $input): array
    {
        return $this->http->post('/partner-api/transactions', [
            ...$input,
            'occurredAt' => $input['occurredAt'] ?? (new \DateTimeImmutable())->format('c'),
        ]);
    }
}
