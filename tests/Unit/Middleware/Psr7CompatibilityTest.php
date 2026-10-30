<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Middleware;

use LSlim\Middleware\CacheHandler;
use LSlim\Middleware\ForwardedRequestHandler;
use LSlim\Middleware\TrailingSlashRemover;
use LSlim\Tests\Support\Psr7;
use LSlim\Tests\Support\RecordingHandler;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class Psr7CompatibilityTest extends TestCase
{
    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testForwardedHeadersWorkAcrossImplementationsWithoutMutatingTheOriginal(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $request = $factory->createServerRequest('GET', 'http://user:secret@example.test/app')
            ->withHeader('x-forwarded-proto', 'https')->withHeader('x-forwarded-port', '8443')
            ->withHeader('X-Forwarded-For', '198.51.100.1, 192.0.2.10, 192.0.2.20');
        $handler = new RecordingHandler($factory->createResponse());
        (new ForwardedRequestHandler())->setTrustedProxyCount(2)->process($request, $handler);
        self::assertSame('https://example.test:8443/app', (string) $handler->request->getUri());
        self::assertSame('192.0.2.10', $handler->request->getAttribute('client-ip'));
        self::assertSame('http://user:secret@example.test/app', (string) $request->getUri());
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testCachePolicyIsPreservedAcrossImplementations(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $original = $factory->createResponse()->withHeader('Cache-Control', 'private, max-age=120')
            ->withHeader('Expires', 'Tue, 14 Nov 2023 22:13:20 GMT');
        $response = (new CacheHandler())->process(
            $factory->createServerRequest('GET', 'http://example.test/'), new RecordingHandler($original)
        );
        self::assertSame($original, $response);
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testTrailingSlashRedirectPreservesTheQueryAcrossImplementations(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $handler = new RecordingHandler($factory->createResponse());
        $response = (new TrailingSlashRemover('/app'))->redirect($factory)->process(
            $factory->createServerRequest('GET', 'https://example.test/app/items/?q=東京'), $handler
        );
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://example.test/app/items?q=%E6%9D%B1%E4%BA%AC', $response->getHeaderLine('Location'));
        self::assertNull($handler->request);
    }
}
