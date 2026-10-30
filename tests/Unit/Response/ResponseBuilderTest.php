<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Response;

use GuzzleHttp\Psr7\Response;
use LSlim\Response\ResponseBuilder;
use LSlim\Tests\Support\TemporaryFiles;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResponseBuilderTest extends TestCase
{
    use TemporaryFiles;

    public static function streamFactories(): iterable
    {
        yield 'default Guzzle stream' => [false];
        yield 'injected PSR-17 factory' => [true];
    }

    #[DataProvider('streamFactories')]
    public function testWritesJsonAndItsByteLength(bool $injectFactory): void
    {
        $original = new Response();
        $builder = new ResponseBuilder($original, $injectFactory ? new Psr17Factory() : null);
        $response = $builder->writeJson(['name' => '東京'], JSON_UNESCAPED_UNICODE)->get();
        self::assertSame('{"name":"東京"}', (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen('{"name":"東京"}'), $response->getHeaderLine('Content-Length'));
        self::assertFalse($original->hasHeader('Content-Type'));
    }

    public function testInvalidJsonInputThrowsInsteadOfReturningAnEmptyResponse(): void
    {
        $this->expectException(RuntimeException::class);
        (new ResponseBuilder(new Response()))->writeJson(["\xB1"]);
    }

    #[DataProvider('streamFactories')]
    public function testServesAFileWithCacheValidators(bool $injectFactory): void
    {
        $path = $this->temporaryFile("日本語\n");
        touch($path, 1700000000);
        $builder = new ResponseBuilder(new Response(), $injectFactory ? new Psr17Factory() : null);
        $response = $builder->writeFile($path, 'text/plain')->get();
        self::assertSame("日本語\n", (string) $response->getBody());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen("日本語\n"), $response->getHeaderLine('Content-Length'));
        self::assertSame('"' . hash_file('sha256', $path) . '"', $response->getHeaderLine('ETag'));
        self::assertSame('Tue, 14 Nov 2023 22:13:20 GMT', $response->getHeaderLine('Last-Modified'));
        $response->getBody()->close();
    }

    public function testFileCanDisableMimeDetectionAndCacheValidators(): void
    {
        $response = (new ResponseBuilder(new Response()))
            ->writeFile($this->temporaryFile('binary data'), false, false)->get();
        self::assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
        self::assertFalse($response->hasHeader('ETag'));
        self::assertFalse($response->hasHeader('Last-Modified'));
        $response->getBody()->close();
    }

    public function testLocalizedAttachmentNameInheritsTheExtension(): void
    {
        $response = (new ResponseBuilder(new Response()))->setAttachment('report.csv', '参加者')->get();
        self::assertSame(
            'attachment; filename="report.csv"; filename*=UTF-8\'\'' . rawurlencode('参加者.csv'),
            $response->getHeaderLine('Content-Disposition')
        );
    }

    public function testRedirectKeepsTheRequestedStatusAndLocation(): void
    {
        $response = (new ResponseBuilder(new Response()))->setRedirect('/complete', 303)->get();
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/complete', $response->getHeaderLine('Location'));
    }

    public function testCachePolicyAndWeakEtag(): void
    {
        $response = (new ResponseBuilder(new Response()))->allowCache(true, 60, true)->setEtag('v1', true)->get();
        self::assertSame('public, max-age=60, must-revalidate', $response->getHeaderLine('Cache-Control'));
        self::assertSame('W/"v1"', $response->getHeaderLine('ETag'));
    }
}
