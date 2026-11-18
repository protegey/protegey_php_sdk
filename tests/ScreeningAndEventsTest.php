<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

class ScreeningAndEventsTest extends ApiTestCase
{
    public function testSanctionsScreenLowercasesTheType(): void
    {
        $result = $this->protegey(['decision' => 'REVIEW', 'score' => 0.71, 'pepMatch' => false, 'matches' => [['source' => 'dfat', 'matchedOn' => 'name']], 'provider' => 'protegey', 'providers' => ['protegey'], 'defaultListChecked' => true, 'degraded' => false], 201)
            ->sanctions->screen('John Doe', 'PERSON');

        $this->assertRequest('POST', '/partner-api/sanctions/screen');
        $this->assertSame(['name' => 'John Doe', 'type' => 'person'], $this->lastBody());
        $this->assertSame('dfat', $result['matches'][0]['source']);
    }

    public function testSanctionsScreenOmitsTypeWhenNotGiven(): void
    {
        $this->protegey(['decision' => 'CLEAR', 'matches' => []], 201)->sanctions->screen('Acme SARL');

        $this->assertSame(['name' => 'Acme SARL'], $this->lastBody());
    }

    public function testSharedSignalCheck(): void
    {
        $response = ['flagged' => true, 'category' => 'CONFIRMED_FRAUD', 'reportedDaysAgo' => 3, 'flagConfidence' => 37, 'matchCount' => 2,
            'matches' => [['category' => 'CONFIRMED_FRAUD', 'reportedDaysAgo' => 3], ['category' => 'IDENTITY_THEFT', 'reportedDaysAgo' => 40]]];
        $result = $this->protegey($response, 201)->sharedSignal->check(['phoneNumber' => '+221771234567', 'governmentId' => 'TAX-1', 'externalCustomerId' => 'cust-1']);

        $this->assertRequest('POST', '/partner-api/shared-signal/check');
        $this->assertSame(['phoneNumber' => '+221771234567', 'governmentId' => 'TAX-1', 'externalCustomerId' => 'cust-1'], $this->lastBody());
        $this->assertSame(2, $result['matchCount']);
        $this->assertSame(['category' => 'IDENTITY_THEFT', 'reportedDaysAgo' => 40], $result['matches'][1]);
    }

    public function testDeviceEventsRecord(): void
    {
        $result = $this->protegey(['recorded' => true, 'action' => 'SOFT_CHALLENGE', 'riskScore' => 15], 201)
            ->deviceEvents->record(['eventId' => 'ev-1', 'visitorId' => 'vis-1', 'externalCustomerId' => 'cust-1', 'deviceAttributes' => ['isRooted' => false]]);

        $this->assertRequest('POST', '/partner-api/device-events');
        $this->assertSame('ev-1', $this->lastBody()['eventId']);
        $this->assertSame('SOFT_CHALLENGE', $result['action']);
    }

    public function testBehavioralEventsRecord(): void
    {
        $result = $this->protegey(['status' => 'LEARNING', 'deviationScore' => 0, 'confidenceTier' => null, 'stepUpRecommended' => false, 'escalatedAlertId' => null], 201)
            ->behavioralEvents->record(['externalCustomerId' => 'cust-1', 'sessionId' => 'sess-1', 'session' => ['loginHourBucket' => 9]]);

        $this->assertRequest('POST', '/partner-api/behavioral-events');
        $this->assertSame(['externalCustomerId' => 'cust-1', 'sessionId' => 'sess-1', 'session' => ['loginHourBucket' => 9]], $this->lastBody());
        $this->assertSame('LEARNING', $result['status']);
    }
}
