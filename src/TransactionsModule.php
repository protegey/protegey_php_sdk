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
     * `country` in a Pan Studio rule to flag cross-border activity), lastPasswordChangeAt /
     * lastEmailChangeAt / lastPhoneChangeAt (ISO 8601 — if you track when a credential last
     * changed, this lets a Pan Studio rule flag a large transaction shortly after, a classic
     * account-takeover pattern; lastPhoneChangeAt also covers a SIM swap for telcos. Omit any of
     * them and the corresponding rule field simply never fires — Protegey never guesses this),
     * accountBalanceBefore (the account balance right before this transaction, if you track it —
     * lets a Pan Studio rule flag a withdrawal/transfer that drains most of the balance, a
     * classic mule-account/account-drain signal; only meaningful for a DEBIT), customerName (the
     * customer's display name, as you know it — self-declared, never verified by Protegey; shows
     * up directly in the Pan Studio transactions table instead of just the opaque
     * externalCustomerId; send it once and it's remembered on this customer's later transactions
     * too, even if you omit it on some of them).
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
