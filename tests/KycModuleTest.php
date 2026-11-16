<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Protegey\Sdk\Client;
use Protegey\Sdk\KycModule;

class KycModuleTest extends TestCase
{
    private function buildClient(MockHandler $mock): Client
    {
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

        return new Client('test-api-key', 'https://api.protegey.test', $guzzle);
    }

    public function testStartSessionPostsTheExternalUserIdAndReturnsTheHostedUrl(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['sessionId' => 'sess_abc', 'url' => 'https://capture.example.test/?enrollmentId=sess_abc&token=cap_8f3a2b1c'])),
        ]);
        $kyc = new KycModule($this->buildClient($mock));

        $result = $kyc->startSession('cust-1');

        $this->assertSame('sess_abc', $result['sessionId']);
        $this->assertSame('https://capture.example.test/?enrollmentId=sess_abc&token=cap_8f3a2b1c', $result['url']);
    }

    public function testStartSessionForwardsEmailAndPhoneWhenGiven(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(201, [], json_encode(['sessionId' => 'sess_abc', 'url' => 'https://capture.example.test/?enrollmentId=sess_abc&token=cap_8f3a2b1c'])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $kyc = new KycModule(new Client('test-api-key', 'https://api.protegey.test', $guzzle));

        $kyc->startSession('cust-1', 'awa@example.com', '+221771234567');

        $sentBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('cust-1', $sentBody['externalUserId']);
        $this->assertSame('awa@example.com', $sentBody['email']);
        $this->assertSame('+221771234567', $sentBody['phone']);
    }

    public function testStartSessionOmitsEmailAndPhoneWhenNotGiven(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(201, [], json_encode(['sessionId' => 'sess_abc', 'url' => 'https://capture.example.test/?enrollmentId=sess_abc&token=cap_8f3a2b1c'])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $kyc = new KycModule(new Client('test-api-key', 'https://api.protegey.test', $guzzle));

        $kyc->startSession('cust-1');

        $sentBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('email', $sentBody);
        $this->assertArrayNotHasKey('phone', $sentBody);
    }

    public function testGetSessionUrlEncodesTheSessionId(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['sessionId' => 'sess/1', 'status' => 'APPROVED'])),
        ]);
        $kyc = new KycModule($this->buildClient($mock));

        $result = $kyc->getSession('sess/1');

        $this->assertSame('APPROVED', $result['status']);
    }
}
