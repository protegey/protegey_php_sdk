# protegey/sdk (PHP)

Official Protegey SDK for PHP backends — the whole Protegey partner API (transactions, cash deposits, alerts,
KYC sessions, 3D liveness, KYB applicants, sanctions screening, the shared fraud signal network, device and
behavioral event relay) plus webhook signature verification, called directly from your server with
your own API key.

Backend-only: this package does not *collect* device fingerprints or behavioral biometrics (those
are browser/mobile concepts) — see `@protegey/sdk` (JS), `protegey_sdk` (Flutter) or
`@protegey/react-native-sdk` for that. It can relay what those collected via
`deviceEvents->record()` / `behavioralEvents->record()`.

## Install

Not yet published to Packagist — install directly from GitHub for now:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/protegey/protegey_php_sdk" }
  ],
  "require": {
    "protegey/sdk": "dev-main"
  }
}
```

```bash
composer install
```

Once published: `composer require protegey/sdk`.

Source: [github.com/protegey/protegey_php_sdk](https://github.com/protegey/protegey_php_sdk)

## Usage

```php
use Protegey\Sdk\Protegey;

$protegey = new Protegey('YOUR_API_KEY', 'https://api.protegey.com');

// Transactions
$result = $protegey->transactions->report([
    'externalTransactionId' => 'tx-00234',
    'externalCustomerId' => 'cust-9981',
    'customerName' => 'Awa Diallo', // self-declared, shows up directly in the Pan Studio transactions table
    'direction' => 'DEBIT',
    'amount' => 250000,
    'currency' => 'XOF',
    'transactionType' => 'cashout',
    'isCash' => true,
]);

// Bank/telco: channel, counterpartyInstitutionCode and counterpartyCountry let Pan Studio rules
// target bank-wire and cross-border scenarios, not just mobile-money structuring.
$protegey->transactions->report([
    'externalTransactionId' => 'tx-00235',
    'externalCustomerId' => 'cust-9981',
    'direction' => 'DEBIT',
    'amount' => 2000000,
    'transactionType' => 'wire',
    'channel' => 'online',
    'counterpartyInstitutionCode' => 'ECOBKTGTG',
    'counterpartyCountry' => 'SN',
]);

// devicePhoneNumber is your customer's own account phone number — you already know it, it's never
// extracted from their device — so it's not covered by the "no device fingerprinting" scope above.
$protegey->transactions->report([
    'externalTransactionId' => 'tx-00236',
    'externalCustomerId' => 'cust-9981',
    'direction' => 'DEBIT',
    'amount' => 15000,
    'transactionType' => 'cashout',
    'devicePhoneNumber' => '+221771234567',
]);

// Identity verification — no manual API call needed, the SDK starts the session and hands back the link
$session = $protegey->kyc->startSession('cust-9981');
// Send $session['url'] to your user however you like (SMS, email, your own hosted redirect page).
// The link is single use and stops working at $session['expiresAt'] (ISO 8601): your capture-link
// lifetime setting in the portal's KYC settings — 30 min, 1 h, 24 h, 3 days or 7 days, 24 h by default.

// $email and $phone are optional — self-declared, never verified by Protegey — and feed
// cross-partner identity-resolution matching
$session = $protegey->kyc->startSession('cust-9981', 'awa@example.com', '+221771234567');

// Polling fallback for the kyc.status_changed webhook (whose sessionId is this same id).
$current = $protegey->kyc->getSession($session['sessionId']);
// status e.g. 'IN_REVIEW' (the final decision), outcome 'MANUAL',
// recommendation 'APPROVE' | 'REVIEW' | 'REJECT' | null (Protegey's own verdict, never changed by an override)

$protegey->kyc->listSessions(['status' => 'IN_REVIEW']);          // SDK converts to the 'In Review' filter value
$protegey->kyc->findByExternalUserId('cust-9981');               // exact match = listSessions(['externalUserId' => ...])
$protegey->kyc->setStatus($session['sessionId'], 'APPROVED', 'Checked by hand'); // = updateSession(status + reviewNote)
$protegey->kyc->updateSession($session['sessionId'], ['fullName' => 'Awa Diallo']); // correct an OCR misread
$protegey->kyc->resendSession($session['sessionId']);            // fresh capture link + new expiresAt
```

Photos come as pre-signed links in `images` (on getSession() and on every listSessions() row):

```php
$images = $current['images'];
// ['documentFront' => ['url' => 'https://...', 'expiresAt' => '2026-11-19T13:00:00.000Z'],
//  'documentBack' => null, 'selfie' => [...], 'documentFaceCrop' => [...]]
```

Each link is valid for **1 hour** and re-issued on every GET — fetch the session again instead of
storing a URL. getSession() still returns the legacy base64 fields (`documentPhoto`,
`documentBackPhoto`, `selfiePhoto`, `documentFaceCrop`) for backward compatibility.

A manual status change (setStatus()/updateSession() or the dashboard) is recorded in
`decision.manualReview` (`reviewedBy`, `reviewedAt`, `previousStatus`, `newStatus`, `note`) and
fires the `kyc.status_changed` webhook like an automatic result.

### 3D liveness

A separate, paid product — billed per check where liveness was proven (KYC is unaffected and stays
free). Two modes: a selfie-only liveness check, or a re-verification that runs the liveness check
and then matches the face against that customer's latest KYC verification.

```php
use Protegey\Sdk\LivenessModule;
use Protegey\Sdk\ProtegeyApiException;

// Selfie-only: is there a live person in front of the camera? externalUserId is optional.
$session = $protegey->liveness->startSession('cust-9981');
// Send/open $session['url'] for your user — single use, valid for 30 minutes

// Re-verification: liveness, then a face match against cust-9981's latest KYC face
try {
    $session = $protegey->liveness->startReverification('cust-9981');
} catch (ProtegeyApiException $e) {
    if ($e->errorCode === LivenessModule::NO_3D_ENROLLMENT) {
        // 400: no KYC face on file for this customer — run a KYC session first
    }
    throw $e;
}

$result = $protegey->liveness->getSession($session['sessionId']);
// status 'PENDING' | 'COMPLETED' | 'EXPIRED', livenessProven true|false|null,
// matchLevel int|null (higher means a stronger match; 0 means a different person), matched true|false|null (re-verification only),
// attempts, checks (one row per attempt, with `billable`), billableChecks, amount (decimal string), currency 'USD'

$protegey->liveness->listSessions(['externalUserId' => 'cust-9981', 'mode' => 'REVERIFICATION']); // page, limit too
```

### Cash deposits

```php
$deposit = $protegey->cashDeposits->record([
    'externalTransactionId' => 'dep-0001', // optional, generated if omitted — send it: a repeat gets a 409
    'externalCustomerId' => 'cust-9981',
    'customerName' => 'Moussa Traoré',
    'amount' => 2500000,
    'currency' => 'XOF',                    // ISO 4217, required
    'channel' => 'BRANCH',
    'branchId' => 'DKR-PLATEAU',
    'location' => 'Dakar, Plateau',
    'depositorName' => 'Awa Diallo',        // who brought the cash, if not the account holder
    'metadata' => ['tellerId' => 'T-04'],
]); // depositedAt defaults to now
// ['cashDepositId' => ..., 'externalTransactionId' => 'dep-0001', 'decision' => 'REVIEW', 'riskScore' => 20, 'alerts' => [...]]

$protegey->cashDeposits->list(['dateFrom' => '2026-11-01', 'minAmount' => 1000000, 'channel' => 'BRANCH', 'limit' => 100]);
// filters: page, limit (≤100), dateFrom, dateTo, externalCustomerId, minAmount, maxAmount,
// currency, channel, decision, branchId, agentId — amount is a decimal string ("2500000.00")
$protegey->cashDeposits->get('dep-0001'); // Protegey cashDepositId or your externalTransactionId, + alerts
```

### Transactions and alerts

```php
$protegey->transactions->list(['decision' => 'BLOCKED', 'externalCustomerId' => 'cust-9981']); // amount is a decimal string
$protegey->transactions->get('tx-00234'); // Protegey transactionId or your externalTransactionId

$alerts = $protegey->alerts->list(['status' => 'OPEN']); // SDK lowercases status/disposition filters
$alert = $protegey->alerts->get($alerts['data'][0]['id']);
$protegey->alerts->update($alert['id'], ['status' => 'DISMISSED', 'disposition' => 'FALSE_POSITIVE']);
['alert' => $alert, 'case' => $case] = $protegey->alerts->convertToCase($alert['id']);
$protegey->alerts->flagTransaction($result['transactionId']); // Protegey uuid, not your external id
```

### KYB applicants

```php
// contactEmail OR contactPhone (at least one). An email is only sent when contactEmail is given —
// otherwise deliver applicationUrl yourself.
$applicant = $protegey->kyb->invite('Jean Mbarga', contactPhone: '+237690000000');
$link = $applicant['applicationUrl'];

$protegey->kyb->listApplicants(['status' => 'PENDING_REVIEW']);
$protegey->kyb->getApplicant($applicant['id']);
$protegey->kyb->resendInvitation($applicant['id']); // returns the submission + a new applicationUrl
$protegey->kyb->screen($applicant['id']);           // sanctions/PEP on the business and its owners
$protegey->kyb->decide($applicant['id'], 'REQUEST_MORE_INFO', 'Please upload your licence'); // or 'APPROVE' / 'REJECT' + reason
```

#### Identity check (KYC) of the person behind an applicant

Once the applicant has submitted their application (400 before), you can ask the person behind it
— or a director — to verify their identity. It's a standard KYC session (billed as one), linked to
the applicant; call it as many times as you need.

```php
$check = $protegey->kyb->requestKycVerification(
    $applicant['id'],
    fullName: 'Jane Sample',        // default: the applicant contact name — compared with the name on the ID
    email: 'jane@example.com',      // Protegey emails the link ONLY when email is given
);
// ['sessionId' => ..., 'kybClientId' => ..., 'url' => ..., 'expiresAt' => ..., 'emailedTo' => 'jane@example.com']
// No email → emailedTo is null: relay $check['url'] yourself. A KYB-linked link is valid at least 7 days.

$protegey->kyb->listKycVerifications($applicant['id']); // also in getApplicant()['kycVerifications']
// ['data' => [['sessionId' => ..., 'status' => 'APPROVED', 'expectedName' => 'Jane Sample',
//   'documentFullName' => 'JANE SAMPLE', 'nameMatch' => 'MATCH', 'livenessScore' => 100, ...]]]
```

`nameMatch` (`kybNameMatch` on `kyc->getSession()` and on the `kyc.status_changed` webhook, which
also carries `kybClientId`) is `MATCH` or `MISMATCH` — order, case and accents ignored, extra middle
names tolerated — or null before a result.

### Screening, shared signals, device and behavioral events

```php
$protegey->sanctions->screen('John Doe', 'person'); // decision BLOCKED | REVIEW | CLEAR

$check = $protegey->sharedSignal->check(['phoneNumber' => '+221771234567', 'externalCustomerId' => 'cust-9981']);
// ['flagged' => true, 'category' => 'CONFIRMED_FRAUD', 'flagConfidence' => 37, 'matchCount' => 2,
//  'matches' => [['category' => 'CONFIRMED_FRAUD', 'reportedDaysAgo' => 3], ...]]

$protegey->deviceEvents->record(['eventId' => 'ev-1', 'visitorId' => $visitorId, 'externalCustomerId' => 'cust-9981']);

// Optional GPS fix (device events and transactions) — a transaction of the same customer within
// ~15 min reuses the one sent with a device event. The transaction response (and get()) returns the
// fix it was scored with, rounded to ~100 m, plus gpsIpDistanceKm, impliedTravelSpeedKmh, isNewGpsArea.
$protegey->deviceEvents->record([
    'eventId' => 'ev-2',
    'visitorId' => $visitorId,
    'externalCustomerId' => 'cust-9981',
    'location' => ['latitude' => 5.3599, 'longitude' => -4.0083, 'accuracyMeters' => 25, 'isMocked' => false],
]);
$protegey->behavioralEvents->record(['externalCustomerId' => 'cust-9981', 'sessionId' => 'sess-1', 'keystroke' => ['avgHoldTimeMs' => 95]]);
```

### Errors

Any non-2xx response throws `Protegey\Sdk\ProtegeyApiException` with `$status`, the API's
`message` (validation messages joined with ", "), `$errorCode` (the API's `errorCode` for any
status — e.g. `PARTNER_SUSPENDED` / `PARTNER_CONTRACT_REQUIRED` on 403 account gating,
`NO_3D_ENROLLMENT` on a 400 from `liveness->startReverification()` — otherwise null) and the
decoded `$body`.

### Casing

Status-like values in responses and webhooks (`status`, `decision`, `decisionVerdict`,
`disposition`, `action`, `deviceAction`, `category`, `outcome`, `ruleDecision`, `confidenceTier`,
`submissionStatus`, `previousStatus`, `newStatus`) are UPPER_SNAKE_CASE, as is KYC
`recommendation`. Other enum-ish fields stay as stored (`ruleSeverity`, `priority`,
`matches[].source` are lowercase). A few filters only accept a specific case
server-side; the SDK normalises those for you (KYC list `status`, alert list `status` /
`disposition`, sanctions `type`, transaction `direction` / `segment`).

Sandbox vs live is decided by which API key you use — there is no header or flag for it.

## Verifying incoming webhooks

```php
use Protegey\Sdk\WebhookVerifier;

$payload = file_get_contents('php://input'); // the RAW body — do not re-encode/re-serialize it
$timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (!WebhookVerifier::verify($payload, $timestamp, $signature, $yourWebhookSecret)) {
    http_response_code(401);
    exit;
}

$event = json_decode($payload, true);
// $event['type']: kyc.status_changed | kyb.status_changed | alert.created | alert.status_changed |
// transaction.blocked | webhook.test. Deliveries are retried (up to 5 attempts over ~3 h) —
// deduplicate on $event['eventId'].
```

- `kyc.status_changed`: `{type, eventId, externalUserId, status, recommendation, sessionId, decision}`
  — fires on an automatic result **and** on a manual status change (dashboard or
  `kyc->setStatus()` / `updateSession()`). `sessionId` is the id `startSession()` returned.
- `kyb.status_changed`: `{type, eventId, clientId, status, submissionStatus, decision}` —
  `submissionStatus` is UPPER_SNAKE_CASE (e.g. `APPROVED`).

The signature is `hex(HMAC-SHA256(secret, "{X-Timestamp}.{rawBody}"))`, compared in constant time.
Pass a fifth argument (e.g. `300`) to also reject deliveries whose timestamp is that many seconds
away from now.

## `baseUrl` — no default, on purpose

Confirm the current value with Protegey before you ship — it can differ between environments and
change independently of this package's version.

## Development

```bash
composer install
composer test
```

## Security note

Keep your API key and webhook secret out of source control, the same way you would any other
secret.
