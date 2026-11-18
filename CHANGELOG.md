## 0.7.0

Brings the SDK in line with the current partner API (all 23 `/partner-api/*` endpoints).

- New modules: `alerts` (`list`, `get`, `update`, `convertToCase`, `flagTransaction`), `kyb` (`listApplicants`, `getApplicant`, `invite`, `resendInvitation`, `screen`, `decide`), `sanctions` (`screen`), `sharedSignal` (`check`), `deviceEvents` (`record`), `behavioralEvents` (`record`).
- `kyc`: new `listSessions()`, `updateSession()`, `resendSession()`. `listSessions()` converts an UPPER_SNAKE_CASE `status` filter to the Title Case value the API requires.
- `transactions`: new `list()` and `get()` (by Protegey id or your `externalTransactionId`). `report()` now uppercases `direction` and `segment`, which the API requires exactly.
- `kyb->invite()` takes `contactEmail` and/or `contactPhone` (at least one) and returns `applicationUrl`; `resendInvitation()` returns the submission plus `applicationUrl`; `decide()` maps the comment to `reason` (REJECT) or `note` (REQUEST_MORE_INFO). Leaked internal submission fields (`tokenHash`, `documentFilePath`) are stripped from KYB responses.
- `alerts->list()` and `sanctions->screen()` lowercase the filters/`type` the API only accepts in lowercase.
- `ProtegeyApiException` gains `$errorCode` (`PARTNER_SUSPENDED` / `PARTNER_CONTRACT_REQUIRED`) and `$body`. Backward compatible.
- `Client` gains `patch()`, and `get()` accepts a query array.
- `WebhookVerifier`: rejects empty headers, tolerates surrounding whitespace / uppercase hex in `X-Signature`, optional `$toleranceSeconds` replay guard, header-name and event-type constants.
- Docs: webhook delivery is retried up to 5 times over ~3 h (not "one retry"); deduplicate on `eventId`.

## 0.6.0

- `kyc->startSession()` accepts two new optional parameters, `$email` and `$phone` — forwarded to the API for cross-partner identity-resolution matching. Self-declared, never verified by Protegey. Fully backward compatible: existing single-argument calls are unaffected.
- `transactions->report()` accepts a new optional key: `devicePhoneNumber` — the customer's own account phone number, as the partner already knows it (never extracted from the device, so this isn't covered by this SDK's "no device fingerprinting" scope). No code change needed on your side: the SDK already forwards any key you give it.

## 0.5.0

- `transactions->report()` accepts a new optional key: `customerName` — shows up directly in the Pan Studio transactions table instead of just the opaque `externalCustomerId`. Self-declared, never verified by Protegey. Send it once and it's remembered on this customer's later transactions too, even if you omit it on some of them. No code change needed on your side: the SDK already forwards any key you give it.

## 0.4.0

- `transactions->report()` accepts a new optional key: `accountBalanceBefore` — lets a Pan Studio rule flag a withdrawal/transfer that drains most of the account balance, a classic mule-account/account-drain signal. Omit it and the corresponding rule field simply never fires. No code change needed on your side: the SDK already forwards any key you give it.

## 0.3.0

- `transactions->report()` accepts new optional keys: `lastPasswordChangeAt`, `lastEmailChangeAt`, `lastPhoneChangeAt` (ISO 8601) — lets a Pan Studio rule flag a large transaction shortly after an account credential change, a classic account-takeover pattern. `lastPhoneChangeAt` also covers a SIM swap for telcos. Omit any of them and the corresponding rule field simply never fires — Protegey never guesses this. No code change needed on your side beyond passing the new keys: the SDK already forwards any key you give it.

## 0.2.0

- `transactions->report()` accepts new optional keys: `channel` ('branch'|'atm'|'pos'|'online'|'mobile_app'|'ussd'|'agent'|'api'|'call_center'), `counterpartyInstitutionCode`, `counterpartyCountry` — lets Pan Studio rules target bank-wire and cross-border scenarios, not just mobile-money structuring. No code change needed on your side beyond passing the new keys: the SDK already forwards any key you give it.

## 0.1.0

- Initial release.
- `Protegey::$transactions->report()` — transaction monitoring.
- `Protegey::$kyc->startSession()` / `Protegey::$kyc->getSession()` — identity verification + webhook-polling fallback.
- `WebhookVerifier::verify()` — HMAC-SHA256 signature verification for incoming webhooks.
- `$baseUrl` is a required constructor parameter, with no built-in default — see the README for why.
