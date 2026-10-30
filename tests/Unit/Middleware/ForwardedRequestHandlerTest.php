<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Middleware;

use GuzzleHttp\Psr7\ServerRequest;
use LSlim\Middleware\ForwardedRequestHandler;
use LSlim\Tests\Support\RecordingHandler;
use PHPUnit\Framework\TestCase;

final class ForwardedRequestHandlerTest extends TestCase
{
    public function testForwardedForIsIgnoredWithoutTrustedProxies(): void
    {
        $handler = new RecordingHandler();
        (new ForwardedRequestHandler())->process(
            new ServerRequest('GET', 'http://example.test/', ['X-Forwarded-For' => '192.0.2.1']),
            $handler
        );
        self::assertNull($handler->request->getAttribute('client-ip'));
    }

    public function testSelectsClientIpFromTheTrustedEndOfTheChain(): void
    {
        $handler = new RecordingHandler();
        $middleware = (new ForwardedRequestHandler())->setTrustedProxyCount(2);
        $middleware->process(
            new ServerRequest('GET', 'http://example.test/', [
                'X-Forwarded-For' => '198.51.100.9, 192.0.2.10, 192.0.2.20',
            ]),
            $handler
        );
        self::assertSame('192.0.2.10', $handler->request->getAttribute('client-ip'));
    }

    public function testSupportsCustomClientIpAttributeAndIpv6(): void
    {
        $handler = new RecordingHandler();
        $middleware = (new ForwardedRequestHandler())->setTrustedProxyCount(1)->setClientIpAddtributeName('ip');
        $middleware->process(
            new ServerRequest('GET', 'http://example.test/', ['X-Forwarded-For' => ' [2001:db8::1] ']),
            $handler
        );
        self::assertSame('2001:db8::1', $handler->request->getAttribute('ip'));
    }

    public function testUsesForwardedSchemeAndPort(): void
    {
        $handler = new RecordingHandler();
        (new ForwardedRequestHandler())->process(
            new ServerRequest('GET', 'http://example.test/', [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '8443',
            ]),
            $handler
        );
        self::assertSame('https://example.test:8443/', (string) $handler->request->getUri());
    }

    public function testRemovesCredentialsFromTheForwardedUriByDefault(): void
    {
        $handler = new RecordingHandler();
        (new ForwardedRequestHandler())->process(new ServerRequest('GET', 'http://user:secret@example.test/'), $handler);
        self::assertSame('', $handler->request->getUri()->getUserInfo());
    }

    public function testCloudFrontSchemeTakesPrecedenceOverForwardedProto(): void
    {
        $handler = new RecordingHandler();
        (new ForwardedRequestHandler())->process(
            new ServerRequest('GET', 'http://example.test:8080/', [
                'CloudFront-Forwarded-Proto' => 'https',
                'X-Forwarded-Proto' => 'http',
            ]),
            $handler
        );
        self::assertSame('https://example.test/', (string) $handler->request->getUri());
    }

    public function testCanKeepCredentialsWhenExplicitlyConfigured(): void
    {
        $handler = new RecordingHandler();
        (new ForwardedRequestHandler())->setClearUserInfo(false)
            ->process(new ServerRequest('GET', 'http://user:secret@example.test/'), $handler);
        self::assertSame('user:secret', $handler->request->getUri()->getUserInfo());
    }
}
