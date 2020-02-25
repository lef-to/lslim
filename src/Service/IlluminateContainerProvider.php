<?php
declare(strict_types=1);
namespace LSlim\Service;

use PImple\ServiceProviderInterface;
use Pimple\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use LSlim\Illuminate\Container as IlluminateContainer;
use LSlim\Illuminate\Config;

class IlluminateContainerProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['laravel'] = static function (Container $c) {
            $ret = new IlluminateContainer();

            $ret->instance('env', $c['env']);

            if (isset($c['db'])) {
                $ret->singleton('db', static function ($app) use ($c) {
                    return $c['db']->getDatabaseManager();
                });
            }

            if (isset($c['queue'])) {
                $ret->singleton('queue', static function ($app) use ($c) {
                    return $c['queue'];
                });
            }

            if (isset($c['cache'])) {
                $ret->singleton('cache', static function ($app) use ($c) {
                    return $c['cache'];
                });
            }

            $ret->singleton('config', static function ($app) use ($c) {
                return new Config();
            });

            $ret->singleton('path.base', static function ($app) use ($c) {
                return $c['base_dir'];
            });

            $ret->singleton('path.storage', static function ($app) use ($c) {
                return $c['var_dir'];
            });

            $ret->singleton('path.config', static function ($app) use ($c) {
                return $c['config_dir'];
            });

            $ret->singleton('path.database', static function ($app) use ($c) {
                if (isset($c['database_dir'])) {
                    return $c['database_dir'];
                }

                return rtrim($c['base_dir'], DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . 'database';
            });

            $ret->instance(ContainerContract::class, $ret);

            return $ret;
        };
    }
}
