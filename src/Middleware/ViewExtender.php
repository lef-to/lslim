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
use Slim\Interfaces\RouteParserInterface;

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
                $runtimeLoader = new TwigRuntimeLoader(
                    $c[RouteParserInterface::class],
                    $request->getUri(),
                    $basePath
                );

                $view->getEnvironment()->addRuntimeLoader($runtimeLoader);
                $view->getEnvironment()->addExtension(new TwigExtension());
            }

            return $view;
        });

        return $handler->handle($request);
    }
}
