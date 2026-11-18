<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

class KycModuleTest extends ApiTestCase
{
    private const URL = 'https://verifykyc.protegey.com/?enrollmentId=sess_abc&token=cap_8f3a2b1c';

    public function testStartSessionPostsTheExternalUserIdAndReturnsTheHostedUrl(): void
    {
        $result = $this->protegey(['sessionId' => 'sess_abc', 'url' => self::URL], 201)->kyc->startSession('cust-1');

        $this->assertRequest('POST', '/partner-api/kyc/sessions');
        $this->assertSame(['externalUserId' => 'cust-1'], $this->lastBody());
        $this->assertSame('sess_abc', $result['sessionId']);
        $this->assertSame(self::URL, $result['url']);
    }

    public function testStartSessionForwardsEmailAndPhoneWhenGiven(): void
    {
        $this->protegey(['sessionId' => 'sess_abc', 'url' => self::URL], 201)->kyc->startSession('cust-1', 'awa@example.com', '+221771234567');

        $this->assertSame(['externalUserId' => 'cust-1', 'email' => 'awa@example.com', 'phone' => '+221771234567'], $this->lastBody());
    }

    public function testListSessionsConvertsAnUppercaseStatusToTheTitleCaseFilterValue(): void
    {
        $page = ['data' => [['sessionId' => 's1', 'status' => 'IN_REVIEW', 'outcome' => 'MANUAL']], 'total' => 1, 'page' => 1, 'limit' => 20, 'totalPages' => 1];
        $result = $this->protegey($page)->kyc->listSessions(['status' => 'IN_REVIEW', 'search' => 'Jane', 'page' => 2]);

        $this->assertRequest('GET', '/partner-api/kyc/sessions');
        $this->assertSame(['status' => 'In Review', 'search' => 'Jane', 'page' => '2'], $this->lastQuery());
        $this->assertSame('IN_REVIEW', $result['data'][0]['status']);
    }

    public function testListSessionsMapsKycExpired(): void
    {
        $this->protegey(['data' => [], 'total' => 0, 'page' => 1, 'limit' => 20, 'totalPages' => 1])->kyc->listSessions(['status' => 'kyc_expired']);

        $this->assertSame('Kyc Expired', $this->lastQuery()['status']);
    }

    public function testGetSessionUrlEncodesTheSessionId(): void
    {
        $result = $this->protegey(['sessionId' => 'sess/1', 'status' => 'APPROVED', 'outcome' => 'SUCCESS'])->kyc->getSession('sess/1');

        $this->assertSame('/partner-api/kyc/sessions/sess%2F1', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('APPROVED', $result['status']);
        $this->assertSame('SUCCESS', $result['outcome']);
    }

    public function testUpdateSessionPatchesTheGivenFields(): void
    {
        $result = $this->protegey(['sessionId' => 's1', 'status' => 'APPROVED', 'outcome' => 'MANUAL', 'decision' => null])
            ->kyc->updateSession('s1', ['status' => 'APPROVED', 'reviewNote' => 'Checked by hand']);

        $this->assertRequest('PATCH', '/partner-api/kyc/sessions/s1');
        $this->assertSame(['status' => 'APPROVED', 'reviewNote' => 'Checked by hand'], $this->lastBody());
        $this->assertSame('APPROVED', $result['status']);
    }

    public function testResendSessionPostsToResend(): void
    {
        $result = $this->protegey(['sessionId' => 's1', 'url' => self::URL], 201)->kyc->resendSession('s1');

        $this->assertRequest('POST', '/partner-api/kyc/sessions/s1/resend');
        $this->assertSame(self::URL, $result['url']);
    }
}
