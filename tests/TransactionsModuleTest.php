<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use Protegey\Sdk\ProtegeyApiException;

class TransactionsModuleTest extends ApiTestCase
{
    private const RESPONSE = ['transactionId' => 'tx-uuid', 'decision' => 'CLEAR', 'riskScore' => 0, 'deviceAction' => null, 'alerts' => []];

    public function testReportDefaultsOccurredAtToNowWhenOmitted(): void
    {
        $result = $this->protegey(self::RESPONSE, 201)->transactions->report([
            'externalTransactionId' => 'tx-1',
            'externalCustomerId' => 'cust-1',
            'direction' => 'DEBIT',
            'amount' => 5000,
            'transactionType' => 'cashout',
        ]);

        $this->assertRequest('POST', '/partner-api/transactions');
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $this->lastBody()['occurredAt']));
        $this->assertSame('tx-uuid', $result['transactionId']);
        $this->assertSame('CLEAR', $result['decision']);
    }

    public function testReportForwardsOptionalFieldsAndUppercasesDirectionAndSegment(): void
    {
        $this->protegey(self::RESPONSE, 201)->transactions->report([
            'externalTransactionId' => 'tx-1',
            'externalCustomerId' => 'cust-1',
            'direction' => 'debit',
            'segment' => 'kyc2',
            'amount' => 2000000,
            'transactionType' => 'wire',
            'occurredAt' => '2026-11-18T10:00:00Z',
            'channel' => 'online',
            'counterpartyInstitutionCode' => 'ECOBKTGTG',
            'counterpartyCountry' => 'SN',
            'visitorId' => 'vis-1',
            'deviceAttributes' => ['platform' => 'android', 'isVpnActive' => true],
        ]);

        $body = $this->lastBody();
        $this->assertSame('DEBIT', $body['direction']);
        $this->assertSame('KYC2', $body['segment']);
        $this->assertSame('2026-11-18T10:00:00Z', $body['occurredAt']);
        $this->assertSame('online', $body['channel']);
        $this->assertSame('ECOBKTGTG', $body['counterpartyInstitutionCode']);
        $this->assertSame('SN', $body['counterpartyCountry']);
        $this->assertSame(['platform' => 'android', 'isVpnActive' => true], $body['deviceAttributes']);
    }

    public function testListSendsFiltersAsQuery(): void
    {
        $page = ['data' => [['transactionId' => 't1', 'amount' => '15000.00', 'decision' => 'REVIEW', 'deviceAction' => 'ALLOW']], 'total' => 1, 'page' => 1, 'limit' => 20, 'totalPages' => 1];
        $result = $this->protegey($page)->transactions->list(['decision' => 'REVIEW', 'externalCustomerId' => 'cust-1', 'dateFrom' => null]);

        $this->assertRequest('GET', '/partner-api/transactions');
        $this->assertSame(['decision' => 'REVIEW', 'externalCustomerId' => 'cust-1'], $this->lastQuery());
        $this->assertSame('15000.00', $result['data'][0]['amount']);
    }

    public function testGetAcceptsAnExternalTransactionId(): void
    {
        $result = $this->protegey(['transactionId' => 't1', 'externalTransactionId' => 'ext 1', 'decision' => 'BLOCKED', 'alerts' => []])->transactions->get('ext 1');

        $this->assertRequest('GET', '/partner-api/transactions/ext%201');
        $this->assertSame('BLOCKED', $result['decision']);
    }

    public function testThrowsOnANonSuccessResponseAndJoinsValidationMessages(): void
    {
        try {
            $this->protegey(['statusCode' => 400, 'message' => ['amount must be a positive number', 'direction must be one of'], 'error' => 'Bad Request'], 400)
                ->transactions->report(['externalTransactionId' => 'tx-1', 'externalCustomerId' => 'cust-1', 'direction' => 'DEBIT', 'amount' => -1, 'transactionType' => 'cashout']);
            $this->fail('Expected ProtegeyApiException');
        } catch (ProtegeyApiException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('amount must be a positive number, direction must be one of', $e->getMessage());
            $this->assertNull($e->errorCode);
        }
    }

    public function testExposesTheErrorCodeOnGatingErrors(): void
    {
        try {
            $this->protegey(['errorCode' => 'PARTNER_CONTRACT_REQUIRED', 'message' => 'Your account has no billing contract configured yet.'], 403)
                ->transactions->report(['externalTransactionId' => 'tx-1', 'externalCustomerId' => 'cust-1', 'direction' => 'DEBIT', 'amount' => 1, 'transactionType' => 'cashout']);
            $this->fail('Expected ProtegeyApiException');
        } catch (ProtegeyApiException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame(ProtegeyApiException::PARTNER_CONTRACT_REQUIRED, $e->errorCode);
        }
    }
}
