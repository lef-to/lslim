<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ForwardedRequestHandler implements MiddlewareInterface
{
    /**
     * @var bool
     */
    protected $clearUserInfo;

    /**
     * @param bool $clearUserInfo
     */
    public function __construct($clearUserInfo = false)
    {
        $this->clearUserInfo = $clearUserInfo;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();

        $proto = $request->getHeaderLine('CLOUDFRONT_FORWARDED_PROTO');
        $port = '';

        if ($proto == '') {
            $proto = $request->getHeaderLine('X_FORWARDED_PROTO');
            $port = $request->getHeaderLine('X_FORWARDED_PORT');
        }

        if ($proto != $uri->getScheme()) {
            if ($proto == 'https') {
                $uri = $uri->withScheme($proto);
                if ($port == '') {
                    $port = 443;
                }
            } elseif ($proto == 'http') {
                $uri = $uri->withScheme($proto);
                if ($port == '') {
                    $port = 80;
                }
            }
        }

        if ($port != '' && $port != $uri->getPort()) {
            $uri = $uri->withPort((int)$port);
        }

        if ($this->clearUserInfo) {
            $uri->withUserInfo('', '');
        }

        $request = $request->withUri($uri);
        return $handler->handle($request);
    }
}
