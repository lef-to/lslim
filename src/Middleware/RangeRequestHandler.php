<?php

declare(strict_types=1);

namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;

class RangeRequestHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }
        $body = $response->getBody();
        $size = $body->getSize();

        if ($size !== null && !$response->hasHeader('Content-Length')) {
            $response = $response->withHeader('Content-Length', (string)$size);
        }

        if ($request->getMethod() !== 'GET' || !$request->hasHeader('Range')) {
            return $response;
        }

        // Support a single byte range; ignore malformed, unknown-unit and multipart requests.
        if (!preg_match('/^bytes=(\d*)-(\d*)$/iD', trim($request->getHeaderLine('Range')), $match)
            || ($match[1] === '' && $match[2] === '')) {
            return $response;
        }
        if ($size === null || $size === 0 || !$body->isSeekable()) {
            return $response;
        }

        if ($match[1] === '') {
            $suffixLength = (int)$match[2];
            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int)$match[1];
            $requestedEnd = $match[2] === '' ? $size - 1 : (int)$match[2];
            if ($requestedEnd < $start && $match[2] !== '') {
                return $response;
            }
            $end = min($requestedEnd, $size - 1);
        }

        if ($start >= $size) {
            return $response->withStatus(416)
                ->withHeader('Content-Range', 'bytes */' . $size)
                ->withHeader('Content-Length', '0')
                ->withBody(Utils::streamFor(''));
        }

        $length = $end - $start + 1;
        if ($length !== $size) {
            $body = new LimitStream($body, $length, $start);
        }
        return $response->withStatus(206, 'Partial Content')
            ->withHeader('Content-Length', (string)$length)
            ->withHeader('Content-Range', sprintf('bytes %d-%d/%d', $start, $end, $size))
            ->withBody($body);
    }
}
