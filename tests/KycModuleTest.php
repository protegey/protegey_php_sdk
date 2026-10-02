<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
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
            new Response(201, [], json_encode(['sessionId' => 'sess_abc', 'url' => 'https://verify.didit.me/session/abc'])),
        ]);
        $kyc = new KycModule($this->buildClient($mock));

        $result = $kyc->startSession('cust-1');

        $this->assertSame('sess_abc', $result['sessionId']);
        $this->assertSame('https://verify.didit.me/session/abc', $result['url']);
    }

    public function testGetSessionUrlEncodesTheSessionId(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['sessionId' => 'sess/1', 'status' => 'Approved'])),
        ]);
        $kyc = new KycModule($this->buildClient($mock));

        $result = $kyc->getSession('sess/1');

        $this->assertSame('Approved', $result['status']);
    }
}
