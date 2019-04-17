<?php
declare(strict_types=1);
namespace LSlim\Util;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Request
{
    /**
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\UriInterface
     */
    public static function makeCurrentUri(RequestInterface $request): UriInterface
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

        return $uri;
    }

    /**
     * @param \Psr\Http\Message\RequestInterface $req
     * @param array $types
     * @return bool
     */
    public static function isAcceptable(RequestInterface $req, array $types)
    {
        $header = $req->getHeaderLine('Accept');
        $selected = array_intersect(explode(',', $header), $types);

        if (count($selected)) {
            return true;
        }

        return false;
    }

    /**
     * @param \Psr\Http\Message\RequestInterface $req
     * @return bool
     */
    public static function isJsonAcceptable(RequestInterface $req)
    {
        return static::isAcceptable(
            $req,
            [
                'application/json',
                'text/json',
                'application/x-json'
            ]
        );
    }

    /**
     * @param \Psr\Http\Message\RequestInterface $req
     * @return bool
     */
    public static function isXmlAcceptable(RequestInterface $req)
    {
        return static::isAcceptable(
            $req,
            [
                'text/xml',
                'application/xml',
                'application/x-xml'
            ]
        );
    }
}
