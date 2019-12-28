<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\LimitStream;
use RuntimeException;

class RangeRequestHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $body = $response->getBody();
        $size = $body->getSize();

        $rangeHeader = $request->getHeader('Range');
        if (!empty($rangeHeader)) {
            if ($size === null) {
                throw new RuntimeException("Invalid stream size.");
            }

            list($toss, $range) = explode('=', $rangeHeader[0]);
            list($start, $end) = explode('-', $range);

            if (empty($end)) {
                $end = $size - 1;
                $range = sprintf('%d-%d', $start, $end);
            }

            $length = (int)$end - (int)$start + 1;

            if ($size != $length) {
                $body = new LimitStream($body, $length, (int)$start);
            }

            return $response
                ->withStatus(206, 'Partial Content')
                ->withHeader('Content-Length', (string)$length)
                ->withHeader('Content-Range', 'bytes ' . $range . '/' . $size)
                ->withBody($body);
        }

        if ($size !== null && !$response->hasHeader('Content-Length')) {
            $response = $response->withHeader('Content-Length', (string)$size);
        }

        return $response;
    }
}
