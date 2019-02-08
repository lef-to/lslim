<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PsrMiddlewareAdapter implements RequestHandlerInterface
{
    /**
     * @var MiddlewareInterface
     */
    private $middleware;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var callable
     */
    private $next;

    /**
     * @param \Psr\Http\Server\MiddlewareInterface $middleware
     */
    public function __construct(MiddlewareInterface $middleware)
    {
        $this->middleware = $middleware;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $this->response = $response;
        $this->next = $next;

        return $this->middleware->process($request, $this);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->next)($request, $this->response);
    }

    public static function adapt(MiddlewareInterface $middleware)
    {
        return new static($middleware);
    }
}
