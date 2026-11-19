<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use Protegey\Sdk\ProtegeyApiException;

class CashDepositsModuleTest extends ApiTestCase
{
    private const ALERT = ['id' => 'al-1', 'alertNumber' => 1043, 'ruleCode' => 'IND006-KYC1', 'status' => 'OPEN', 'decisionVerdict' => 'REVIEW', 'triggeredAt' => '2026-11-18T09:30:01.204Z'];

    public function testRecordPostsTheDepositAsIs(): void
    {
        $result = $this->protegey(['cashDepositId' => 'cd-uuid', 'externalTransactionId' => 'dep-0001', 'decision' => 'REVIEW', 'riskScore' => 20, 'alerts' => [self::ALERT]], 201)
            ->cashDeposits->record([
                'externalTransactionId' => 'dep-0001',
                'externalCustomerId' => 'cust-9981',
                'amount' => 2500000,
                'currency' => 'XOF',
                'channel' => 'BRANCH',
                'branchId' => 'DKR-PLATEAU',
                'depositorName' => 'Awa Diallo',
                'metadata' => ['tellerId' => 'T-04'],
            ]);

        $this->assertRequest('POST', '/partner-api/cash-deposits');
        $body = $this->lastBody();
        $this->assertSame('dep-0001', $body['externalTransactionId']);
        $this->assertSame(2500000, $body['amount']);
        $this->assertSame(['tellerId' => 'T-04'], $body['metadata']);
        $this->assertArrayNotHasKey('depositedAt', $body); // server defaults it to the time of the call
        $this->assertSame('cd-uuid', $result['cashDepositId']);
        $this->assertSame('REVIEW', $result['decision']);
        $this->assertSame('OPEN', $result['alerts'][0]['status']);
    }

    public function testRecordSurfacesA409OnADuplicateExternalTransactionId(): void
    {
        try {
            $this->protegey(['statusCode' => 409, 'message' => 'Transaction already submitted', 'error' => 'Conflict'], 409)
                ->cashDeposits->record(['externalTransactionId' => 'dep-0001', 'externalCustomerId' => 'cust-9981', 'amount' => 1000, 'currency' => 'XOF']);
            $this->fail('Expected ProtegeyApiException');
        } catch (ProtegeyApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('Transaction already submitted', $e->getMessage());
        }
    }

    public function testListSendsEveryFilterAsQueryAndKeepsAmountAsAString(): void
    {
        $page = ['data' => [['cashDepositId' => 'cd-1', 'amount' => '2500000.00', 'channel' => 'BRANCH', 'decision' => 'REVIEW']], 'total' => 1, 'page' => 1, 'limit' => 100, 'totalPages' => 1];
        $result = $this->protegey($page)->cashDeposits->list([
            'page' => 1,
            'limit' => 100,
            'dateFrom' => '2026-11-01T00:00:00Z',
            'dateTo' => '2026-11-30T23:59:59Z',
            'externalCustomerId' => 'cust-9981',
            'minAmount' => 1000000,
            'maxAmount' => 5000000,
            'currency' => 'XOF',
            'channel' => 'BRANCH',
            'decision' => 'REVIEW',
            'branchId' => 'DKR-PLATEAU',
            'agentId' => null,
        ]);

        $this->assertRequest('GET', '/partner-api/cash-deposits');
        $this->assertSame([
            'page' => '1',
            'limit' => '100',
            'dateFrom' => '2026-11-01T00:00:00Z',
            'dateTo' => '2026-11-30T23:59:59Z',
            'externalCustomerId' => 'cust-9981',
            'minAmount' => '1000000',
            'maxAmount' => '5000000',
            'currency' => 'XOF',
            'channel' => 'BRANCH',
            'decision' => 'REVIEW',
            'branchId' => 'DKR-PLATEAU',
        ], $this->lastQuery());
        $this->assertSame('2500000.00', $result['data'][0]['amount']);
    }

    public function testGetAcceptsAnExternalTransactionIdAndReturnsAlerts(): void
    {
        $result = $this->protegey(['cashDepositId' => 'cd-1', 'externalTransactionId' => 'dep 1', 'amount' => '1000.00', 'alerts' => [self::ALERT]])->cashDeposits->get('dep 1');

        $this->assertRequest('GET', '/partner-api/cash-deposits/dep%201');
        $this->assertSame('1000.00', $result['amount']);
        $this->assertCount(1, $result['alerts']);
    }
}
