<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use LSlim\Middleware\CacheHandler;
use LSlim\Tests\Support\RecordingHandler;
use PHPUnit\Framework\TestCase;

final class CacheHandlerTest extends TestCase
{
    public function testDisablesCachingByDefault(): void
    {
        $response = (new CacheHandler())->process(
            new ServerRequest('GET', 'http://example.test/'),
            new RecordingHandler(new Response(201, [], 'body'))
        );
        self::assertSame('no-store,no-cache', $response->getHeaderLine('Cache-Control'));
        self::assertTrue($response->hasHeader('Expires'));
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('body', (string) $response->getBody());
    }

    public function testCanEnablePublicCaching(): void
    {
        $before = time();
        $response = (new CacheHandler('public', 60))->process(
            new ServerRequest('GET', 'http://example.test/'), new RecordingHandler()
        );
        self::assertSame('public, max-age=60', $response->getHeaderLine('Cache-Control'));
        $expires = strtotime($response->getHeaderLine('Expires'));
        self::assertGreaterThanOrEqual($before + 60, $expires);
        self::assertLessThanOrEqual(time() + 60, $expires);
    }

    public function testPreservesAnExplicitResponseCachePolicy(): void
    {
        $original = new Response(200, [
            'Cache-Control' => 'private, max-age=120',
            'Expires' => 'Tue, 14 Nov 2023 22:13:20 GMT',
        ]);
        $response = (new CacheHandler())->process(
            new ServerRequest('GET', 'http://example.test/'), new RecordingHandler($original)
        );
        self::assertSame($original, $response);
    }
}
