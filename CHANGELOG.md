## 0.10.0

- `kyb`: new `requestKycVerification($clientId, ?$fullName, ?$email, ?$phone, ?$externalUserId)` (POST `/partner-api/kyb/applicants/{clientId}/kyc-sessions` — a standard KYC session linked to the applicant; returns `sessionId`, `kybClientId`, `url`, `expiresAt`, `emailedTo`; the link is emailed only when `email` is given, `emailedTo` is null otherwise; 400 until the applicant has submitted) and `listKycVerifications($clientId)` (`data[]`: sessionId, status, outcome, recommendation, expectedName, documentFullName, nameMatch MATCH | MISMATCH | null, livenessScore, faceMatchScore, livemode, createdAt, updatedAt). `getApplicant()` also returns these rows as `kycVerifications`.
- `kyb`: new `importApplicants($applicants, $dryRun = false)` (POST `/partner-api/kyb/applicants/import` — 1 to 1000 existing clients, same columns as the portal CSV template; created already submitted, never emailed; invalid/duplicate rows reported in `errors` [row, field, code, error] and skipped; returns `totalRows`, `importedCount`, `rejectedCount`, `errors`, `importId`) and `getImport($importId)` (background screening progress: status SCREENING | COMPLETED, screenedCount, flaggedCount, flaggedClientIds — Protegey never rejects a match for you). `listApplicants()` accepts an `importId` filter; applicants carry `source` (invitation | import), `externalReference` and `importBatchId`.
- `kyc`: sessions carry `kybClientId` (null for a plain session) on list rows, `getSession()` and `updateSession()`; `getSession()` also carries `kybNameMatch` and `decision.documentFullName`. The `kyc.status_changed` webhook carries `kybClientId` and `kybNameMatch`.
- `kyc->startSession()` / `resendSession()` return `expiresAt`. The capture link lifetime is no longer a fixed 30 minutes: it's your capture-link lifetime setting in the portal's KYC settings (30 min, 1 h, 24 h, 3 days or 7 days), 24 h by default; a KYB-linked check always gets at least 7 days. 3D liveness links are unchanged (30 min).
- `transactions->report()` and `deviceEvents->record()` accept an optional `location` (`latitude`, `longitude`, `accuracyMeters?`, `capturedAt?`, `isMocked?`). The transaction response and `transactions->get()` return `location` (rounded to ~100 m, with `isMocked`, `gpsIpDistanceKm`, `impliedTravelSpeedKmh`, `isNewGpsArea`) or null. No code change needed on your side: the SDK already forwards any key you give it.

## 0.9.0

- New `liveness` module (3D liveness — a separate, paid product billed per check where liveness was proven; KYC is unaffected and stays free): `startSession(?$externalUserId)` (mode LIVENESS, selfie-only), `startReverification($externalUserId, ?$referenceVerificationId)` (mode REVERIFICATION — liveness then a face match against the customer's latest KYC face), `getSession()` (status PENDING | COMPLETED | EXPIRED, livenessProven, matchLevel, matched, checks, billableChecks, amount in USD) and `listSessions()` (page, limit, externalUserId, mode). Both start methods return the hosted capture `url` (single use, 30 min).
- `ProtegeyApiException::NO_3D_ENROLLMENT` (also `LivenessModule::NO_3D_ENROLLMENT`): the 400 `errorCode` of a re-verification for a customer with no KYC face. `$errorCode` is documented as set for any status whose body carries one (it already was, in practice).

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
