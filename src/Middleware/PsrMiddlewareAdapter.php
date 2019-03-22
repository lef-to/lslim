<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
        $handler = new class($response, $next) implements RequestHandlerInterface {
            /**
             * @var \Psr\Http\Message\ResponseInterface
             */
            private $response;

            /**
             * @var callable
             */
            private $next;

            public function __construct(ResponseInterface $response, callable $next)
            {
                $this->response = $response;
                $this->next = $next;
            }

            /**
             * @inheritdoc
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->next)($request, $this->response);
            }
        };

        return $this->middleware->process($request, $handler);
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
