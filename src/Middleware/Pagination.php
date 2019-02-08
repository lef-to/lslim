<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Uri;
use Illuminate\Pagination\Paginator;

class Pagination
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $c = $this->container;
        Paginator::viewFactoryResolver(
            function () use ($c) {
                return new Pagination\ViewFactory($c->view);
            }
        );

        Paginator::currentPathResolver(
            function () use ($request) {
                $uri = $request->getUri();
                if ($uri instanceof Uri) {
                    return ltrim($uri->getBasePath(), '/') . '/' . ltrim($uri->getPath(), '/');
                }
                return ltrim($uri->getPath(), '/');
            }
        );

        Paginator::currentPageResolver(
            function ($pageName = 'page') use ($request) {
                $query = $request->getQueryParams();
                $page = $query[$pageName] ?? 1;

                if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page > 1) {
                    return $page;
                }
                return 1;
            }
        );

        return $next($request, $response);
    }
}
