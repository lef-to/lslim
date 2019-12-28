<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Pagination\Paginator;
use Slim\Views\Twig as View;

class Pagination
{
    public static function setup(ServerRequestInterface $request, View $view)
    {
        Paginator::viewFactoryResolver(
            function () use ($view) {
                return new Pagination\ViewFactory($view);
            }
        );

        Paginator::currentPathResolver(
            function () use ($request) {
                $uri = $request->getUri();
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
    }
}
