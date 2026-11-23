<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use Protegey\Sdk\LivenessModule;
use Protegey\Sdk\ProtegeyApiException;

class LivenessModuleTest extends ApiTestCase
{
    private const URL = 'https://capture.example.test/liveness?session=lv_abc&token=cap_8f3a2b1c';

    private function created(string $mode, ?string $externalUserId): array
    {
        return ['sessionId' => 'lv_abc', 'mode' => $mode, 'status' => 'PENDING', 'externalUserId' => $externalUserId, 'url' => self::URL, 'captureUrl' => self::URL, 'expiresInMinutes' => 30];
    }

    public function testStartSessionPostsLivenessModeAndReturnsTheHostedUrl(): void
    {
        $result = $this->protegey($this->created('LIVENESS', 'cust-1'), 201)->liveness->startSession('cust-1');

        $this->assertRequest('POST', '/partner-api/liveness/sessions');
        $this->assertSame(['mode' => 'LIVENESS', 'externalUserId' => 'cust-1'], $this->lastBody());
        $this->assertSame('lv_abc', $result['sessionId']);
        $this->assertSame(self::URL, $result['url']);
        $this->assertSame('PENDING', $result['status']);
        $this->assertSame(30, $result['expiresInMinutes']);
    }

    public function testStartSessionOmitsTheExternalUserIdWhenNotGiven(): void
    {
        $this->protegey($this->created('LIVENESS', null), 201)->liveness->startSession();

        $this->assertSame(['mode' => 'LIVENESS'], $this->lastBody());
    }

    public function testStartReverificationPostsReverificationModeWithTheExternalUserId(): void
    {
        $result = $this->protegey($this->created('REVERIFICATION', 'cust-1'), 201)->liveness->startReverification('cust-1');

        $this->assertRequest('POST', '/partner-api/liveness/sessions');
        $this->assertSame(['mode' => 'REVERIFICATION', 'externalUserId' => 'cust-1'], $this->lastBody());
        $this->assertSame('REVERIFICATION', $result['mode']);
    }

    public function testStartReverificationForwardsTheReferenceVerificationIdWhenGiven(): void
    {
        $this->protegey($this->created('REVERIFICATION', 'cust-1'), 201)->liveness->startReverification('cust-1', 'sess_kyc_1');

        $this->assertSame(['mode' => 'REVERIFICATION', 'externalUserId' => 'cust-1', 'referenceVerificationId' => 'sess_kyc_1'], $this->lastBody());
    }

    public function testStartReverificationWithoutAKycFaceThrowsNo3dEnrollment(): void
    {
        $body = ['errorCode' => 'NO_3D_ENROLLMENT', 'message' => 'No 3D record (KYC) exists for this user — run a KYC session first'];
        $protegey = $this->protegey($body, 400);

        try {
            $protegey->liveness->startReverification('cust-404');
            $this->fail('Expected a ProtegeyApiException');
        } catch (ProtegeyApiException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame(LivenessModule::NO_3D_ENROLLMENT, $e->errorCode);
            $this->assertSame('NO_3D_ENROLLMENT', ProtegeyApiException::NO_3D_ENROLLMENT);
            $this->assertSame($body['message'], $e->getMessage());
            $this->assertSame($body, $e->body);
        }
    }

    public function testGetSessionUrlEncodesTheSessionIdAndReturnsTheResultAndBilling(): void
    {
        $session = [
            'id' => 'uuid-1', 'sessionId' => 'lv/1', 'mode' => 'REVERIFICATION', 'status' => 'COMPLETED',
            'externalUserId' => 'cust-1', 'referenceEnrollmentId' => 'sess_kyc_1', 'livenessProven' => true,
            'matchLevel' => 15, 'matched' => true, 'duplicateFaceEnrollmentIds' => null, 'attempts' => 2,
            'isSandbox' => false, 'completedAt' => '2026-11-23T10:05:00.000Z', 'createdAt' => '2026-11-23T10:00:00.000Z',
            'billableChecks' => 1, 'amount' => '0.50', 'currency' => 'USD',
            'checks' => [
                ['id' => 'c1', 'livenessProven' => false, 'matchLevel' => null, 'billable' => false, 'unitPrice' => '0.50', 'currency' => 'USD', 'createdAt' => '2026-11-23T10:02:00.000Z'],
                ['id' => 'c2', 'livenessProven' => true, 'matchLevel' => 15, 'billable' => true, 'unitPrice' => '0.50', 'currency' => 'USD', 'createdAt' => '2026-11-23T10:05:00.000Z'],
            ],
        ];
        $result = $this->protegey($session)->liveness->getSession('lv/1');

        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame('/partner-api/liveness/sessions/lv%2F1', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('COMPLETED', $result['status']);
        $this->assertTrue($result['livenessProven']);
        $this->assertSame(15, $result['matchLevel']);
        $this->assertTrue($result['matched']);
        $this->assertSame(1, $result['billableChecks']);
        $this->assertSame('0.50', $result['amount']);
        $this->assertCount(2, $result['checks']);
        $this->assertFalse($result['checks'][0]['billable']);
    }

    public function testListSessionsForwardsFiltersAndUppercasesTheMode(): void
    {
        $page = ['data' => [['sessionId' => 'lv_1', 'mode' => 'LIVENESS', 'status' => 'EXPIRED']], 'total' => 1, 'page' => 2, 'limit' => 20, 'totalPages' => 1];
        $result = $this->protegey($page)->liveness->listSessions(['page' => 2, 'externalUserId' => 'cust-1', 'mode' => 'liveness']);

        $this->assertRequest('GET', '/partner-api/liveness/sessions');
        $this->assertSame(['page' => '2', 'externalUserId' => 'cust-1', 'mode' => 'LIVENESS'], $this->lastQuery());
        $this->assertSame('EXPIRED', $result['data'][0]['status']);
        $this->assertSame(1, $result['total']);
    }
}
