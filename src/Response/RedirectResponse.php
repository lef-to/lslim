<?php
declare(strict_types=1);
namespace LSlim\Response;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;

class RedirectResponse
{
    public static function create(ResponseInterface $response, $url, $code = 302)
    {
        return $response
            ->withStatus($code)
            ->withHeader('Location', (string)$url);
    }

    public static function createPathFor(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $name,
        $data = [],
        $queryParams = [],
        $code = 302
    ) {
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $url = $routeParser->urlFor($name, $data, $queryParams);
        return static::create($response, $url, $code);
    }
}
