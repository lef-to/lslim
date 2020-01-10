<?php
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class NyholmPsr17Provider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container[Psr17Factory::class] = function (Container $c) {
            return new Psr17Factory();
        };

        $container[ResponseFactoryInterface::class] = function (Container $c) {
            return $c[Psr17Factory::class];
        };

        $container[StreamFactoryInterface::class] = function (Container $c) {
            return $c[Psr17Factory::class];
        };

        $container[ServerRequestCreatorInterface::class] = function (Container $c) {
            $psr17Factory = $c[Psr17Factory::class];

            $creator = new ServerRequestCreator(
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
                $psr17Factory
            );

            return new class ($creator) implements ServerRequestCreatorInterface {
                private $creator;

                public function __construct(ServerRequestCreator $creator)
                {
                    $this->creator = $creator;
                }

                public function createServerRequestFromGlobals(): ServerRequestInterface
                {
                    return $this->creator->fromGlobals();
                }
            };
        };
    }
}
