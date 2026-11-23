<?php

declare(strict_types=1);

namespace Protegey\Sdk;

use GuzzleHttp\ClientInterface;

/**
 * The Protegey SDK for PHP backends — the whole partner API (transactions, cash deposits, alerts, KYC sessions,
 * 3D liveness, KYB applicants, sanctions screening, shared fraud signals, device and behavioral event relay),
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
 * $liveness = $protegey->liveness->startReverification('cust-1');
 * ```
 *
 * $baseUrl has no default — confirm the current value with Protegey (it may differ between
 * environments and can change independently of this package's version). Sandbox vs live is
 * decided by which API key you pass.
 */
class Protegey
{
    public readonly TransactionsModule $transactions;
    public readonly CashDepositsModule $cashDeposits;
    public readonly AlertsModule $alerts;
    public readonly KycModule $kyc;
    public readonly LivenessModule $liveness;
    public readonly KybModule $kyb;
    public readonly SanctionsModule $sanctions;
    public readonly SharedSignalModule $sharedSignal;
    public readonly DeviceEventsModule $deviceEvents;
    public readonly BehavioralEventsModule $behavioralEvents;

    public function __construct(string $apiKey, string $baseUrl, ?ClientInterface $httpClient = null)
    {
        $http = new Client($apiKey, $baseUrl, $httpClient);
        $this->transactions = new TransactionsModule($http);
        $this->cashDeposits = new CashDepositsModule($http);
        $this->alerts = new AlertsModule($http);
        $this->kyc = new KycModule($http);
        $this->liveness = new LivenessModule($http);
        $this->kyb = new KybModule($http);
        $this->sanctions = new SanctionsModule($http);
        $this->sharedSignal = new SharedSignalModule($http);
        $this->deviceEvents = new DeviceEventsModule($http);
        $this->behavioralEvents = new BehavioralEventsModule($http);
    }
}
