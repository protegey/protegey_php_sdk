<?php

declare(strict_types=1);

namespace Protegey\Sdk\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Protegey\Sdk\Protegey;
use Psr\Http\Message\RequestInterface;

/** Builds a Protegey instance over a Guzzle MockHandler and records every request sent. */
abstract class ApiTestCase extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    protected array $history = [];

    /** @param array<string, mixed>|list<mixed> $responseBody */
    protected function protegey(array $responseBody = [], int $status = 200): Protegey
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([new Response($status, [], json_encode($responseBody))]));
        $stack->push(Middleware::history($this->history));

        return new Protegey('test-api-key', 'https://api.protegey.test/', new GuzzleClient(['handler' => $stack]));
    }

    protected function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    /** @return array<string, mixed> */
    protected function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true) ?? [];
    }

    /** @return array<string, string> */
    protected function lastQuery(): array
    {
        parse_str($this->lastRequest()->getUri()->getQuery(), $query);

        return $query;
    }

    protected function assertRequest(string $method, string $path): void
    {
        $request = $this->lastRequest();
        $this->assertSame($method, $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());
        $this->assertSame('test-api-key', $request->getHeaderLine('x-api-key'));
        $this->assertFalse($request->hasHeader('Authorization'));
    }
}
