# protegey/sdk (PHP)

Official Protegey SDK for PHP backends — the whole Protegey partner API (transactions, alerts,
KYC sessions, KYB applicants, sanctions screening, the shared fraud signal network, device and
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
// Send $session['url'] to your user however you like (SMS, email, your own hosted redirect page)

// $email and $phone are optional — self-declared, never verified by Protegey — and feed
// cross-partner identity-resolution matching
$session = $protegey->kyc->startSession('cust-9981', 'awa@example.com', '+221771234567');

// Polling fallback — use this if you're not sure a webhook delivery ever arrived (the
// kyc.status_changed webhook carries no usable session id, only externalUserId).
$current = $protegey->kyc->getSession($session['sessionId']); // status e.g. 'IN_REVIEW', outcome 'MANUAL'

$protegey->kyc->listSessions(['status' => 'IN_REVIEW']);          // SDK converts to the 'In Review' filter value
$protegey->kyc->updateSession($session['sessionId'], ['status' => 'APPROVED', 'reviewNote' => 'Checked by hand']);
$protegey->kyc->resendSession($session['sessionId']);            // fresh 30-minute capture link
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

### Screening, shared signals, device and behavioral events

```php
$protegey->sanctions->screen('John Doe', 'person'); // decision BLOCKED | REVIEW | CLEAR

$check = $protegey->sharedSignal->check(['phoneNumber' => '+221771234567', 'externalCustomerId' => 'cust-9981']);
// ['flagged' => true, 'category' => 'CONFIRMED_FRAUD', 'flagConfidence' => 37, 'matchCount' => 2,
//  'matches' => [['category' => 'CONFIRMED_FRAUD', 'reportedDaysAgo' => 3], ...]]

$protegey->deviceEvents->record(['eventId' => 'ev-1', 'visitorId' => $visitorId, 'externalCustomerId' => 'cust-9981']);
$protegey->behavioralEvents->record(['externalCustomerId' => 'cust-9981', 'sessionId' => 'sess-1', 'keystroke' => ['avgHoldTimeMs' => 95]]);
```

### Errors

Any non-2xx response throws `Protegey\Sdk\ProtegeyApiException` with `$status`, the API's
`message` (validation messages joined with ", "), `$errorCode` (`PARTNER_SUSPENDED` /
`PARTNER_CONTRACT_REQUIRED` on 403 account gating, otherwise null) and the decoded `$body`.

### Casing

Status-like values in responses and webhooks (`status`, `decision`, `decisionVerdict`,
`disposition`, `action`, `deviceAction`, `category`, `outcome`, `ruleDecision`, `confidenceTier`)
are UPPER_SNAKE_CASE. Other enum-ish fields stay as stored (`ruleSeverity`, `priority`,
`matches[].source`, `submissionStatus` are lowercase). A few filters only accept a specific case
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
