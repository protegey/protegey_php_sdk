<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use Protegey\Sdk\ProtegeyApiException;

class KybModuleTest extends ApiTestCase
{
    private const BUSINESS = ['id' => 'c1', 'contactName' => 'John', 'contactEmail' => null, 'contactPhone' => '+237690000000', 'status' => 'INVITED'];
    private const SUBMISSION = ['id' => 's1', 'status' => 'PENDING', 'tokenHash' => 'deadbeef', 'documentFilePath' => 'kyb/x.pdf', 'documentFileName' => 'x.pdf'];

    public function testInviteByPhoneOnlyReturnsTheApplicationUrl(): void
    {
        $result = $this->protegey([...self::BUSINESS, 'applicationUrl' => 'https://portal.example.test/client-application?token=t'], 201)
            ->kyb->invite('John', contactPhone: '+237690000000');

        $this->assertRequest('POST', '/partner-api/kyb/applicants');
        $this->assertSame(['contactName' => 'John', 'contactPhone' => '+237690000000'], $this->lastBody());
        $this->assertSame('https://portal.example.test/client-application?token=t', $result['applicationUrl']);
    }

    public function testInviteForwardsEmailAndTemplateId(): void
    {
        $this->protegey(self::BUSINESS, 201)->kyb->invite('John', 'john@acme.test', null, 'tpl-1');

        $this->assertSame(['contactName' => 'John', 'contactEmail' => 'john@acme.test', 'templateId' => 'tpl-1'], $this->lastBody());
    }

    public function testInviteRequiresAnEmailOrAPhone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->protegey()->kyb->invite('John');
    }

    public function testListAndGetStripLeakedInternalSubmissionFields(): void
    {
        $page = ['data' => [[...self::BUSINESS, 'submission' => self::SUBMISSION], [...self::BUSINESS, 'submission' => null]], 'total' => 2, 'page' => 1, 'limit' => 20, 'totalPages' => 1];
        $list = $this->protegey($page)->kyb->listApplicants(['status' => 'INVITED', 'stage' => 'invited']);

        $this->assertRequest('GET', '/partner-api/kyb/applicants');
        $this->assertSame(['status' => 'INVITED', 'stage' => 'invited'], $this->lastQuery());
        $this->assertArrayNotHasKey('tokenHash', $list['data'][0]['submission']);
        $this->assertArrayNotHasKey('documentFilePath', $list['data'][0]['submission']);
        $this->assertSame('x.pdf', $list['data'][0]['submission']['documentFileName']);
        $this->assertNull($list['data'][1]['submission']);

        $one = $this->protegey([...self::BUSINESS, 'submission' => self::SUBMISSION])->kyb->getApplicant('c1');
        $this->assertRequest('GET', '/partner-api/kyb/applicants/c1');
        $this->assertArrayNotHasKey('tokenHash', $one['submission']);
    }

    public function testResendInvitationReturnsTheSubmissionWithApplicationUrl(): void
    {
        $result = $this->protegey([...self::SUBMISSION, 'applicationUrl' => 'https://portal.example.test/client-application?token=new'], 201)
            ->kyb->resendInvitation('c1');

        $this->assertRequest('POST', '/partner-api/kyb/applicants/c1/resend-invitation');
        $this->assertSame('https://portal.example.test/client-application?token=new', $result['applicationUrl']);
        $this->assertArrayNotHasKey('tokenHash', $result);
    }

    public function testScreen(): void
    {
        $result = $this->protegey(['business' => ['decision' => 'REVIEW'], 'owners' => [], 'provider' => 'protegey', 'degraded' => false])->kyb->screen('c1');

        $this->assertRequest('GET', '/partner-api/kyb/applicants/c1/screen');
        $this->assertSame('REVIEW', $result['business']['decision']);
    }

    public function testScreenExposesNetworkFlagGroupEntities(): void
    {
        $flag = ['label' => 'Business phone', 'flagged' => true, 'matchCount' => 1, 'flagConfidence' => 40, 'groupEntities' => ['Sample Pay Senegal']];
        $result = $this->protegey(['business' => ['decision' => 'CLEAR'], 'owners' => [], 'networkFlags' => [$flag]])->kyb->screen('c1');

        $this->assertSame(['Sample Pay Senegal'], $result['networkFlags'][0]['groupEntities']);
    }

    public function testDecideMapsTheCommentToReasonOrNote(): void
    {
        $this->protegey([...self::BUSINESS, 'status' => 'REJECTED'])->kyb->decide('c1', 'reject', 'Shell company');
        $this->assertRequest('PATCH', '/partner-api/kyb/applicants/c1/decide');
        $this->assertSame(['decision' => 'REJECT', 'reason' => 'Shell company'], $this->lastBody());

        $result = $this->protegey([...self::BUSINESS, 'status' => 'MORE_INFO_REQUIRED', 'applicationUrl' => 'https://x/?token=t'])
            ->kyb->decide('c1', 'REQUEST_MORE_INFO', 'Upload your licence');
        $this->assertSame(['decision' => 'REQUEST_MORE_INFO', 'note' => 'Upload your licence'], $this->lastBody());
        $this->assertSame('https://x/?token=t', $result['applicationUrl']);

        $this->protegey([...self::BUSINESS, 'status' => 'ACTIVE'])->kyb->decide('c1', 'APPROVE', 'ignored');
        $this->assertSame(['decision' => 'APPROVE'], $this->lastBody());
    }

    public function testRequestKycVerificationForwardsOnlyTheGivenFields(): void
    {
        $created = ['sessionId' => 'k1', 'kybClientId' => 'c1', 'url' => 'https://capture.example.test/?enrollmentId=k1', 'expiresAt' => '2026-11-26T09:00:00.000Z', 'emailedTo' => 'jane@example.test'];
        $result = $this->protegey($created, 201)->kyb->requestKycVerification('c1', 'Jane Sample', 'jane@example.test');

        $this->assertRequest('POST', '/partner-api/kyb/applicants/c1/kyc-sessions');
        $this->assertSame(['fullName' => 'Jane Sample', 'email' => 'jane@example.test'], $this->lastBody());
        $this->assertSame('2026-11-26T09:00:00.000Z', $result['expiresAt']);
        $this->assertSame('jane@example.test', $result['emailedTo']);

        $result = $this->protegey([...$created, 'emailedTo' => null], 201)->kyb->requestKycVerification('c1', externalUserId: 'director_17');
        $this->assertSame(['externalUserId' => 'director_17'], $this->lastBody());
        $this->assertNull($result['emailedTo']);
    }

    public function testRequestKycVerificationWithNoFieldsSendsAnEmptyObject(): void
    {
        $this->protegey(['sessionId' => 'k1', 'emailedTo' => null], 201)->kyb->requestKycVerification('c1');

        $this->assertSame('{}', (string) $this->lastRequest()->getBody());
    }

    public function testRequestKycVerificationBeforeSubmissionThrows(): void
    {
        try {
            $this->protegey(['statusCode' => 400, 'message' => 'The applicant has not submitted their application yet'], 400)
                ->kyb->requestKycVerification('c1');
            $this->fail('Expected ProtegeyApiException');
        } catch (ProtegeyApiException $e) {
            $this->assertSame(400, $e->status);
            $this->assertStringContainsString('not submitted', $e->getMessage());
        }
    }

    public function testListKycVerifications(): void
    {
        $row = ['sessionId' => 'k1', 'status' => 'APPROVED', 'expectedName' => 'Jane Sample', 'documentFullName' => 'JANE SAMPLE', 'nameMatch' => 'MATCH', 'livemode' => true];
        $result = $this->protegey(['data' => [$row]])->kyb->listKycVerifications('c1');

        $this->assertRequest('GET', '/partner-api/kyb/applicants/c1/kyc-sessions');
        $this->assertSame('MATCH', $result['data'][0]['nameMatch']);
    }

    public function testImportApplicantsPostsTheRowsAndReturnsTheReport(): void
    {
        $rows = [
            ['legalName' => 'Acme SARL', 'country' => 'CI', 'contactName' => 'Jane Sample', 'contactEmail' => 'jane@example.test', 'externalReference' => 'crm-1'],
            ['legalName' => 'Beta SA', 'country' => 'SN', 'contactName' => 'John Sample'],
        ];
        $report = ['totalRows' => 2, 'importedCount' => 1, 'rejectedCount' => 1, 'errors' => [['row' => 1, 'field' => null, 'code' => 'contact_required', 'error' => 'contactEmail or contactPhone is required']], 'importId' => 'imp-1'];
        $result = $this->protegey($report)->kyb->importApplicants($rows);

        $this->assertRequest('POST', '/partner-api/kyb/applicants/import');
        $this->assertSame(['applicants' => $rows], $this->lastBody());
        $this->assertSame('contact_required', $result['errors'][0]['code']);
        $this->assertSame('imp-1', $result['importId']);

        $this->protegey([...$report, 'importId' => null])->kyb->importApplicants($rows, dryRun: true);
        $this->assertTrue($this->lastBody()['dryRun']);
    }

    public function testImportApplicantsRequires1To1000Rows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->protegey()->kyb->importApplicants([]);
    }

    public function testGetImportAndListByImportId(): void
    {
        $result = $this->protegey(['importId' => 'imp-1', 'status' => 'COMPLETED', 'flaggedCount' => 1, 'flaggedClientIds' => ['c1'], 'completedAt' => '2026-11-26T09:00:00.000Z'])
            ->kyb->getImport('imp-1');
        $this->assertRequest('GET', '/partner-api/kyb/imports/imp-1');
        $this->assertSame(['c1'], $result['flaggedClientIds']);

        $page = ['data' => [[...self::BUSINESS, 'source' => 'import', 'externalReference' => 'crm-1', 'importBatchId' => 'imp-1', 'submission' => null]], 'total' => 1, 'page' => 1, 'limit' => 20, 'totalPages' => 1];
        $list = $this->protegey($page)->kyb->listApplicants(['importId' => 'imp-1']);
        $this->assertSame(['importId' => 'imp-1'], $this->lastQuery());
        $this->assertSame('import', $list['data'][0]['source']);
    }
}
