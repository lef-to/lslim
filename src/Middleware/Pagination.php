<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Uri;
use Illuminate\Pagination\Paginator;

class Pagination
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        Paginator::viewFactoryResolver(
            function () {
                return new Pagination\ViewFactory($this->container->get('view'));
            }
        );

        Paginator::currentPathResolver(
            function () use ($request) {
                $uri = $request->getUri();
                if ($uri instanceof Uri) {
                    return $uri->getBasePath() . '/' . ltrim($uri->getPath(), '/');
                }
                return '/' . trim($uri->getPath(), '/');
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
