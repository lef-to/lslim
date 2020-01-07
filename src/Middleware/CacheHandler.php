<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Micheh\Cache\CacheUtil;

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

    public function __construct($maxAge = 0, $type = 'private')
    {
        $this->maxAge = $maxAge;
        $this->type = $type;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $util = new CacheUtil();

        if (!$response->hasHeader('Cache-Control')) {
            if ($this->maxAge == 0) {
                $response = $util->withCachePrevention($response);
            } else {
                $response = $util->withCache($response, $this->type == 'public', $this->maxAge);
            }
        }

        if ($util->isNotModified($request, $response)) {
            return $response->withStatus(304);
        }

        return $response;
    }
}
