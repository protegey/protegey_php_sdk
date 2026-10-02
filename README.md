# protegey/sdk (PHP)

Official Protegey SDK for PHP backends — transaction reporting, identity verification sessions,
and webhook signature verification, called directly from your server with your own API key.

Backend-only: no device fingerprinting or behavioral biometrics here (those are browser/mobile
concepts) — see `@protegey/sdk` (JS), `protegey_sdk` (Flutter) or `@protegey/react-native-sdk` for
those.

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
    'direction' => 'DEBIT',
    'amount' => 250000,
    'currency' => 'XOF',
    'transactionType' => 'cashout',
    'isCash' => true,
]);

// Identity verification — no manual API call needed, the SDK starts the session and hands back the link
$session = $protegey->kyc->startSession('cust-9981');
// Send $session['url'] to your user however you like (SMS, email, your own hosted redirect page)

// Polling fallback — webhook delivery is best-effort (one retry, no queue), so use this if you're
// not sure a delivery ever arrived, or just want to double-check a session's status.
$current = $protegey->kyc->getSession($session['sessionId']);
```

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
```

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
