## 0.8.0

- New `cashDeposits` module: `record()` (POST `/partner-api/cash-deposits` — a repeated `externalTransactionId` throws a 409 `ProtegeyApiException`), `list()` (filters page, limit ≤ 100, dateFrom, dateTo, externalCustomerId, minAmount, maxAmount, currency, channel, decision, branchId, agentId) and `get()` (by Protegey `cashDepositId` or your `externalTransactionId`, with alerts). `amount` comes back as a decimal string.
- `kyc`: `recommendation` (APPROVE | REVIEW | REJECT | null — Protegey's verdict, unchanged by a manual override) and an `images` block (`documentFront`, `documentBack`, `selfie`, `documentFaceCrop`, each `{url, expiresAt}` or null — pre-signed links valid 1 h, re-issued on every GET) documented on `listSessions()` / `getSession()`; `updateSession()` returns `recommendation` too.
- `kyc->listSessions()` accepts an exact `externalUserId` filter; new shorthands `findByExternalUserId()` and `setStatus($sessionId, $status, $reviewNote)`.
- Docs fixes: `kyc.status_changed` also fires on a manual status change, its `sessionId` is the session id (not null) and it carries `recommendation`; `kyb.status_changed` `submissionStatus` and `decision.manualReview.previousStatus` / `newStatus` are UPPER_SNAKE_CASE. Webhooks: up to 5 attempts over ~3 h, deduplicate on `eventId`.

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
