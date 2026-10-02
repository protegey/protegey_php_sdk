<?php

declare(strict_types=1);

namespace Protegey\Sdk;

use GuzzleHttp\ClientInterface;

/**
 * The Protegey SDK for PHP backends — transaction reporting and identity verification sessions,
 * called directly from your server with your own API key, plus a webhook signature verifier.
 *
 * ```php
 * $protegey = new Protegey('YOUR_API_KEY', 'https://api.protegey.com');
 * $result = $protegey->transactions->report([
 *     'externalTransactionId' => 'tx-1',
 *     'externalCustomerId' => 'cust-1',
 *     'direction' => 'DEBIT',
 *     'amount' => 5000,
 *     'transactionType' => 'cashout',
 * ]);
 * $session = $protegey->kyc->startSession('cust-1');
 * ```
 *
 * $baseUrl has no default — confirm the current value with Protegey (it may differ between
 * environments and can change independently of this package's version).
 */
class Protegey
{
    public readonly TransactionsModule $transactions;
    public readonly KycModule $kyc;

    public function __construct(string $apiKey, string $baseUrl, ?ClientInterface $httpClient = null)
    {
        $http = new Client($apiKey, $baseUrl, $httpClient);
        $this->transactions = new TransactionsModule($http);
        $this->kyc = new KycModule($http);
    }
}
