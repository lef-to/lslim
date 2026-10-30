<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Middleware;

use GuzzleHttp\Psr7\PumpStream;
use LSlim\Middleware\RangeRequestHandler;
use LSlim\Tests\Support\Psr7;
use LSlim\Tests\Support\RecordingHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class RangeRequestHandlerTest extends TestCase
{
    private function response(string $implementation, ?string $range, string $body = '0123456789', string $method = 'GET'): ResponseInterface
    {
        $factory = Psr7::factory($implementation);
        $request = $factory->createServerRequest($method, 'http://example.test/file');
        if ($range !== null) {
            $request = $request->withHeader('Range', $range);
        }
        return (new RangeRequestHandler())->process($request, new RecordingHandler(
            $factory->createResponse()->withHeader('Content-Type', 'text/plain')->withBody($factory->createStream($body))
        ));
    }

    #[DataProvider('ranges')]
    public function testReturnsRequestedBytes(string $implementation, string $range, string $expectedBody, string $expectedRange): void
    {
        $response = $this->response($implementation, $range);
        self::assertSame(206, $response->getStatusCode());
        self::assertSame($expectedBody, (string) $response->getBody());
        self::assertSame((string) strlen($expectedBody), $response->getHeaderLine('Content-Length'));
        self::assertSame($expectedRange, $response->getHeaderLine('Content-Range'));
    }

    public static function ranges(): iterable
    {
        foreach (Psr7::implementations() as $name => [$implementation]) {
            foreach ([
                'first byte' => ['bytes=0-0', '0', 'bytes 0-0/10'],
                'middle' => ['bytes=2-5', '2345', 'bytes 2-5/10'],
                'open end' => ['bytes=7-', '789', 'bytes 7-9/10'],
                'whole file' => ['bytes=0-9', '0123456789', 'bytes 0-9/10'],
                'suffix' => ['bytes=-3', '789', 'bytes 7-9/10'],
                'long suffix' => ['bytes=-20', '0123456789', 'bytes 0-9/10'],
                'end beyond file' => ['bytes=7-100', '789', 'bytes 7-9/10'],
                'leading zero' => ['bytes=002-005', '2345', 'bytes 2-5/10'],
                'case insensitive unit' => ['BYTES=2-5', '2345', 'bytes 2-5/10'],
                'huge end' => ['bytes=7-' . str_repeat('9', 40), '789', 'bytes 7-9/10'],
            ] as $case => $values) {
                yield $name . ': ' . $case => [$implementation, ...$values];
            }
        }
    }

    #[DataProvider('invalidRanges')]
    public function testIgnoresMalformedOrUnsupportedRanges(string $implementation, string $range): void
    {
        $response = $this->response($implementation, $range);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('0123456789', (string) $response->getBody());
        self::assertSame('10', $response->getHeaderLine('Content-Length'));
        self::assertFalse($response->hasHeader('Content-Range'));
    }

    public static function invalidRanges(): iterable
    {
        foreach (Psr7::implementations() as $name => [$implementation]) {
            foreach (['bytes=', 'bytes=-', 'bytes=a-b', 'items=0-1', 'bytes=5-2', 'bytes=0-1,4-5', 'bytes=1-2garbage'] as $range) {
                yield $name . ': ' . $range => [$implementation, $range];
            }
        }
    }

    #[DataProvider('unsatisfiableRanges')]
    public function testUnsatisfiableRangeReturns416WithTheFullSize(string $implementation, string $range): void
    {
        $response = $this->response($implementation, $range);
        self::assertSame(416, $response->getStatusCode());
        self::assertSame('bytes */10', $response->getHeaderLine('Content-Range'));
        self::assertSame('0', $response->getHeaderLine('Content-Length'));
        self::assertSame('', (string) $response->getBody());
    }

    public static function unsatisfiableRanges(): iterable
    {
        foreach (Psr7::implementations() as $name => [$implementation]) {
            foreach (['bytes=10-', 'bytes=50-100', 'bytes=-0', 'bytes=' . str_repeat('9', 40) . '-'] as $range) {
                yield $name . ': ' . $range => [$implementation, $range];
            }
        }
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testUnknownSizeIgnoresRangeAndPreservesTheFullStreamingResponse(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $chunks = ['first chunk', ' second chunk', false];
        $body = new PumpStream(static function () use (&$chunks) {
            return array_shift($chunks);
        });
        self::assertNull($body->getSize());
        $original = $factory->createResponse()->withHeader('Content-Type', 'text/plain')->withBody($body);
        $response = (new RangeRequestHandler())->process(
            $factory->createServerRequest('GET', 'http://example.test/stream')->withHeader('Range', 'bytes=2-5'),
            new RecordingHandler($original)
        );
        self::assertSame($original, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Content-Range'));
        self::assertFalse($response->hasHeader('Content-Length'));
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('first chunk second chunk', $response->getBody()->getContents());
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testEmptyFileCanIgnoreRangeAndHasZeroLength(string $implementation): void
    {
        $response = $this->response($implementation, 'bytes=0-0', '');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('0', $response->getHeaderLine('Content-Length'));
        self::assertSame('', (string) $response->getBody());
        self::assertFalse($response->hasHeader('Content-Range'));
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testWithoutRangeKeepsTheResponseAndAddsItsByteLength(string $implementation): void
    {
        $response = $this->response($implementation, null, '日本語');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('日本語', (string) $response->getBody());
        self::assertSame((string) strlen('日本語'), $response->getHeaderLine('Content-Length'));
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testIgnoresRangeForOtherMethodsAndNon200Responses(string $implementation): void
    {
        foreach (['POST', 'HEAD'] as $method) {
            $response = $this->response($implementation, 'bytes=0-0', method: $method);
            self::assertSame(200, $response->getStatusCode());
            self::assertFalse($response->hasHeader('Content-Range'));
        }
        $factory = Psr7::factory($implementation);
        $original = $factory->createResponse(404)->withBody($factory->createStream('not found'));
        $response = (new RangeRequestHandler())->process(
            $factory->createServerRequest('GET', 'http://example.test/')->withHeader('Range', 'bytes=0-0'),
            new RecordingHandler($original)
        );
        self::assertSame($original, $response);
    }
}
