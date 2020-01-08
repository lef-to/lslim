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

    public function __construct($type = 'no-cache', $maxAge = 0)
    {
        $this->type = $type;
        $this->maxAge = $maxAge;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $util = new CacheUtil();

        $maxAge = $this->maxAge;
        if (!$response->hasHeader('Cache-Control')) {
            $type = $this->type;
            if ($type == 'public') {
                $response = $util->withCache($response, true, $maxAge);
            } elseif ($type == 'private') {
                $response = $util->withCache($response, false, $maxAge);
            } else {
                $response = $util->withCachePrevention($response);
                $maxAge = 0;
            }
        }

        if (!$response->hasHeader('Expires')) {
            $response = $util->withRelativeExpires($response, $maxAge);
        }

        if ($util->isNotModified($request, $response)) {
            return $response->withStatus(304);
        }

        return $response;
    }
}
