<?php
declare(stirct_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Csrf\Guard;

class CsrfWrapper implements MiddlewareInterface
{
    /**
     * @var \Slim\Csrf\Guard
     */
    protected $guard;

    public function __construct(Guard $guard)
    {
        $this->guard = $guard;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('csrf', $this->guard);
        return $this->guard->process($request, $handler);
    }
}
