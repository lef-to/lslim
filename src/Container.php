<?php
declare(strict_types=1);
namespace LSlim;

use Psr\Container\ContainerInterface;
use Pimple\Container as PImpleContainer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Slim\Interfaces\RouteParserInterface;

class Container extends PImpleContainer implements ContainerInterface
{
    public function __construct($env, $values = [])
    {
        parent::__construct($values);
        $this->offsetSet('env', $env);
    }

    public function get($id)
    {
        return $this->offsetGet($id);
    }

    public function has($id)
    {
        return $this->offsetExists($id);
    }

    public function createApplication($basePath = '', $detectSlimHttp = false): App
    {
        if ($this->has(ResponseFactoryInterface::class)) {
            AppFactory::setResponseFactory($this->get(ResponseFactoryInterface::class));
        }
        if ($this->has(StreamFactoryInterface::class)) {
            AppFactory::setStreamFactory($this->get(StreamFactoryInterface::class));
        }
        AppFactory::setContainer($this);
        AppFactory::setSlimHttpDecoratorsAutomaticDetection($detectSlimHttp);

        if ($this->has(ServerRequestCreatorInterface::class)) {
            ServerRequestCreatorFactory::setServerRequestCreator($this->get(ServerRequestCreatorInterface::class));
        }
        ServerRequestCreatorFactory::setSlimHttpDecoratorsAutomaticDetection($detectSlimHttp);

        $app = AppFactory::create();
        if ($basePath) {
            $app->setBasePath($basePath);
        }

        $this->offsetSet(RouteParserInterface::class, $app->getRouteCollector()->getRouteParser());

        return $app;
    }
}
