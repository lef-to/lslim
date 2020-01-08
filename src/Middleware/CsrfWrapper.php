<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Csrf\Guard;

class CsrfWrapper implements MiddlewareInterface
{
    /**
     * @var \Psr\Http\Message\ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @var int|null
     */
    protected $storageLimit = null;

    /**
     * @var callable|null
     */
    protected $failureHandler = null;

    /**
     * @var \Slim\Csrf\Guard|null
     */
    protected $guard = null;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function setStorageLimit($limit): self
    {
        $this->storageLimit = $limit;
        return $this;
    }

    public function setFailureHandler(callable $handler): self
    {
        $this->failureHandler = $handler;
        return $this;
    }

    public function getGuard(): ?Guard
    {
        return $this->guard;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $guard = new Guard($this->responseFactory);

        if ($this->storageLimit !== null) {
            $guard->setStorageLimit($this->storageLimit);
        }

        if ($this->failureHandler !== null) {
            $guard->setFailureHandler($this->failureHandler);
        }

        $this->guard = $guard;

        $request = $request->withAttribute('csrf', $guard);
        return $guard->process($request, $handler);
    }
}
