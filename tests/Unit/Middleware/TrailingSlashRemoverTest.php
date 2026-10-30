<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Middleware;

use GuzzleHttp\Psr7\ServerRequest;
use LSlim\Middleware\TrailingSlashRemover;
use LSlim\Tests\Support\RecordingHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrailingSlashRemoverTest extends TestCase
{
    #[DataProvider('paths')]
    public function testNormalizesPathsWhilePreservingTheBaseRoot(?string $basePath, string $path, string $expected): void
    {
        $handler = new RecordingHandler();
        (new TrailingSlashRemover($basePath))->process(new ServerRequest('GET', 'http://example.test' . $path), $handler);
        self::assertSame($expected, $handler->request->getUri()->getPath());
        self::assertSame('q=1', $handler->request->getUri()->getQuery());
    }

    public static function paths(): iterable
    {
        yield 'root' => [null, '/?q=1', '/'];
        yield 'trailing slash' => [null, '/items/?q=1', '/items'];
        yield 'already normalized' => [null, '/items?q=1', '/items'];
        yield 'base root' => ['/app', '/app/?q=1', '/app/'];
        yield 'child below base' => ['/app', '/app/items/?q=1', '/app/items'];
    }

    public function testOptionalRedirectPreservesTheQueryString(): void
    {
        $handler = new RecordingHandler();
        $response = (new TrailingSlashRemover())->redirect(new Psr17Factory())->process(
            new ServerRequest('GET', 'http://example.test/items/?q=1'), $handler
        );
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('http://example.test/items?q=1', $response->getHeaderLine('Location'));
        self::assertNull($handler->request);
    }
}
