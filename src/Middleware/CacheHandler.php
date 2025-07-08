<?php

declare(strict_types=1);

namespace LSlim\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\HttpCache\CacheProvider;

class CacheHandler implements MiddlewareInterface
{
    /**
     * @var int
     */
    protected $maxAge;

    /**
     * @var string
     */
    protected $type;

    public function __construct($type = 'no-cache', $maxAge = 0)
    {
        $this->type = $type;
        $this->maxAge = $maxAge;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response   = $handler->handle($request);
        $maxAge     = $this->maxAge;
        $provider   = new CacheProvider();

        if (!$response->hasHeader('Cache-Control')) {
            $type = $this->type;
            if ($type === 'public' || $type === 'private') {
                $response = $provider->allowCache($response, $type, $maxAge);
            } else {
                $response = $provider->denyCache($response);
                $maxAge = 0;
            }
        }

        if (!$response->hasHeader('Expires')) {
            $response = $provider->withExpires($response, time() + $maxAge);
        }

        return $response;
    }
}
