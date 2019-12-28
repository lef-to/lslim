<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Pimple\Container;
use Slim\Views\Twig as View;
use Slim\Views\TwigExtension;
use Slim\Views\TwigRuntimeLoader;
use Slim\Routing\RouteContext;

class ViewExtender implements MiddlewareInterface
{
    /**
     * @var \Pimple\Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct(Container $container, $basePath = '')
    {
        $this->container = $container;
        $this->basePath = $basePath;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $basePath = $this->basePath;
        $this->container->extend('view', static function (View $view, Container $c) use ($request, $basePath) {
            if (!$view->getEnvironment()->hasExtension(TwigExtension::class)) {
                $routeContext = RouteContext::fromRequest($request);
                $runtimeLoader = new TwigRuntimeLoader(
                    $routeContext->getRouteParser(),
                    $request->getUri(),
                    $basePath
                );

                $view->getEnvironment()->addRuntimeLoader($runtimeLoader);
                $view->getEnvironment()->addExtension(new TwigExtension());
            }

            if (!isset($view['csrf'])) {
                $csrf = $request->getAttribute('csrf');
                if ($csrf !== null) {
                    $view['csrf'] = [
                        'key' => [
                            'name'  => $csrf->getTokenNameKey(),
                            'value' => $csrf->getTokenValueKey()
                        ],
                        'name'  => $csrf->getTokenName(),
                        'value' => $csrf->getTokenValue()
                    ];
                }
            }

            return $view;
        });

        return $handler->handle($request);
    }
}
