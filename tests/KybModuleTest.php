<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

class KybModuleTest extends ApiTestCase
{
    private const BUSINESS = ['id' => 'c1', 'contactName' => 'John', 'contactEmail' => null, 'contactPhone' => '+237690000000', 'status' => 'INVITED'];
    private const SUBMISSION = ['id' => 's1', 'status' => 'PENDING', 'tokenHash' => 'deadbeef', 'documentFilePath' => 'kyb/x.pdf', 'documentFileName' => 'x.pdf'];

    public function testInviteByPhoneOnlyReturnsTheApplicationUrl(): void
    {
        $result = $this->protegey([...self::BUSINESS, 'applicationUrl' => 'https://partner.protegey.com/client-application?token=t'], 201)
            ->kyb->invite('John', contactPhone: '+237690000000');

        $this->assertRequest('POST', '/partner-api/kyb/applicants');
        $this->assertSame(['contactName' => 'John', 'contactPhone' => '+237690000000'], $this->lastBody());
        $this->assertSame('https://partner.protegey.com/client-application?token=t', $result['applicationUrl']);
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
        $result = $this->protegey([...self::SUBMISSION, 'applicationUrl' => 'https://partner.protegey.com/client-application?token=new'], 201)
            ->kyb->resendInvitation('c1');

        $this->assertRequest('POST', '/partner-api/kyb/applicants/c1/resend-invitation');
        $this->assertSame('https://partner.protegey.com/client-application?token=new', $result['applicationUrl']);
        $this->assertArrayNotHasKey('tokenHash', $result);
    }

    public function testScreen(): void
    {
        $result = $this->protegey(['business' => ['decision' => 'REVIEW'], 'owners' => [], 'provider' => 'protegey', 'degraded' => false])->kyb->screen('c1');

        $this->assertRequest('GET', '/partner-api/kyb/applicants/c1/screen');
        $this->assertSame('REVIEW', $result['business']['decision']);
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
}
