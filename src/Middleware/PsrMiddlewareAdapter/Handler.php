<?php
declare(strict_types=1);
namespace LSlim\Middleware\PsrMiddlewareAdapter;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Handler implements RequestHandlerInterface
{
    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var callable
     */
    private $next;

    /**
     * @param ResponseInterface $response
     * @param callable $next
     */
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
}
