<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

class AlertsModuleTest extends ApiTestCase
{
    private const ALERT = [
        'id' => 'a1', 'alertNumber' => 1032, 'ruleCode' => 'VEL-001', 'status' => 'OPEN', 'decisionVerdict' => 'ALERT',
        'disposition' => null, 'ruleSeverity' => 'review', 'ruleName' => 'Velocity',
        'transaction' => ['amount' => '15000.00', 'currency' => 'XOF', 'direction' => 'DEBIT', 'transactionType' => 'transfer', 'occurredAt' => '2026-11-18T10:00:00.000Z'],
    ];

    public function testListLowercasesStatusAndDispositionFilters(): void
    {
        $result = $this->protegey(['data' => [self::ALERT], 'total' => 1, 'page' => 1, 'limit' => 20, 'totalPages' => 1])
            ->alerts->list(['status' => 'MORE_INFO_REQUESTED', 'disposition' => 'FALSE_POSITIVE', 'alertNumber' => 1032]);

        $this->assertRequest('GET', '/partner-api/alerts');
        $this->assertSame(['status' => 'more_info_requested', 'disposition' => 'false_positive', 'alertNumber' => '1032'], $this->lastQuery());
        $this->assertSame('VEL-001', $result['data'][0]['ruleCode']);
    }

    public function testGet(): void
    {
        $result = $this->protegey(self::ALERT)->alerts->get('a1');

        $this->assertRequest('GET', '/partner-api/alerts/a1');
        $this->assertSame('15000.00', $result['transaction']['amount']);
    }

    public function testUpdatePatchesAndKeepsExplicitNulls(): void
    {
        $this->protegey([...self::ALERT, 'status' => 'DISMISSED', 'disposition' => 'FALSE_POSITIVE'])
            ->alerts->update('a1', ['status' => 'DISMISSED', 'disposition' => 'FALSE_POSITIVE', 'assignedToUserId' => null]);

        $this->assertRequest('PATCH', '/partner-api/alerts/a1');
        $this->assertSame(['status' => 'DISMISSED', 'disposition' => 'FALSE_POSITIVE', 'assignedToUserId' => null], $this->lastBody());
    }

    public function testConvertToCase(): void
    {
        $result = $this->protegey(['alert' => [...self::ALERT, 'disposition' => 'ESCALATED'], 'case' => ['id' => 'c1', 'caseNumber' => 48, 'status' => 'OPEN', 'priority' => 'medium']], 201)
            ->alerts->convertToCase('a1');

        $this->assertRequest('POST', '/partner-api/alerts/a1/convert-to-case');
        $this->assertSame('ESCALATED', $result['alert']['disposition']);
        $this->assertSame(48, $result['case']['caseNumber']);
    }

    public function testFlagTransaction(): void
    {
        $this->protegey([...self::ALERT, 'ruleCode' => 'MANUAL-FLAG-001', 'decisionVerdict' => null], 201)->alerts->flagTransaction('tx-uuid');

        $this->assertRequest('POST', '/partner-api/alerts/flag-transaction');
        $this->assertSame(['transactionId' => 'tx-uuid'], $this->lastBody());
    }
}
