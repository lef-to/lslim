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
    protected $clearUserInfo = true;

    /**
     * @var int
     */
    protected $trustedProxyCount = 0;

    /**
     * @var string
     */
    protected $clientIpAttributeName = 'client-ip';

    /**
     * @param bool $clearUserInfo
     */
    public function __construct()
    {
    }

    public function setClearUserInfo(bool $value): static
    {
        $this->clearUserInfo = $value;
        return $this;
    }

    public function setTrustedProxyCount(int $value): static
    {
        $this->trustedProxyCount = $value;
        return $this;
    }

    public function setClientIpAddtributeName(string $value): static
    {
        $this->clientIpAttributeName = $value;
        return $this;
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

        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwarded)) {
            $forwarded = explode(',', $forwarded);
            $forwarded_cnt = count($forwarded);

            if ($forwarded_cnt) {
                if ($this->trustedProxyCount) {
                    $cnt = min($forwarded_cnt, $this->trustedProxyCount);
                    if ($cnt !== $forwarded_cnt) {
                        $forwarded = array_slice($forwarded, -$cnt);
                    }
                }

                $request = $request->withAttribute(
                    $this->clientIpAttributeName,
                    trim($forwarded[0], " []")
                );
            }
        }

        return $handler->handle($request);
    }
}
