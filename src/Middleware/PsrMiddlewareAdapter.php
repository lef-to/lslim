<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use LSlim\Middleware\PsrMiddlewareAdapter\Handler;

class PsrMiddlewareAdapter
{
    /**
     * @var MiddlewareInterface
     */
    private $middleware;

    /**
     * @param \Psr\Http\Server\MiddlewareInterface $middleware
     */
    public function __construct(MiddlewareInterface $middleware)
    {
        $this->middleware = $middleware;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): ResponseInterface {
        return $this->middleware->process($request, new Handler($response, $next));
    }

    /**
     * @param \Psr\Http\Server\MiddlewareInterface $middleware
     * @return callable
     */
    public static function adapt(MiddlewareInterface $middleware)
    {
        return new static($middleware);
    }
}
