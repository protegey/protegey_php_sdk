<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Protegey\Sdk\Client;
use Protegey\Sdk\TransactionsModule;

class TransactionsModuleTest extends TestCase
{
    public function testReportDefaultsOccurredAtToNowWhenOmitted(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['transactionId' => 'tx-1', 'decision' => 'clear', 'riskScore' => 0, 'alerts' => [], 'deviceAction' => null])),
        ]);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $transactions = new TransactionsModule(new Client('key', 'https://api.protegey.test', $guzzle));

        $result = $transactions->report([
            'externalTransactionId' => 'tx-1',
            'externalCustomerId' => 'cust-1',
            'direction' => 'DEBIT',
            'amount' => 5000,
            'transactionType' => 'cashout',
        ]);

        $this->assertSame('tx-1', $result['transactionId']);
        $this->assertSame('clear', $result['decision']);
    }

    public function testThrowsOnANonSuccessResponse(): void
    {
        $this->expectException(\Protegey\Sdk\ProtegeyApiException::class);

        $mock = new MockHandler([new Response(422, [], json_encode(['message' => 'amount must be positive']))]);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $transactions = new TransactionsModule(new Client('key', 'https://api.protegey.test', $guzzle));

        $transactions->report([
            'externalTransactionId' => 'tx-1',
            'externalCustomerId' => 'cust-1',
            'direction' => 'DEBIT',
            'amount' => -1,
            'transactionType' => 'cashout',
        ]);
    }
}
