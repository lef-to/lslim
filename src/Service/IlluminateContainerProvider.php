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

            $ret->instance('lslim.container', $c);
            $ret->instance('env', $c['env']);

            if (isset($c['db'])) {
                $ret->singleton('db', static function ($app) {
                    $c = $app['lslim.container'];
                    return $c['db']->getDatabaseManager();
                });
            }

            if (isset($c['redis'])) {
                $ret->singleton('redis', static function ($app) {
                    $c = $app['lslim.container'];
                    return $c['redis'];
                });

                $ret->bind('redis.connection', static function ($app) {
                    return $app['redis']->connection();
                });
            }

            if (isset($c['queue'])) {
                $ret->singleton('queue', static function ($app) {
                    $c = $app['lslim.container'];
                    return $c['queue'];
                });
            }

            if (isset($c['cache'])) {
                $ret->singleton('cache', static function ($app) {
                    $c = $app['lslim.container'];
                    return $c['cache'];
                });
            }

            $ret->singleton('config', static function ($app) {
                return new Config();
            });

            $ret->singleton('path.base', static function ($app) {
                $c = $app['lslim.container'];
                return $c['base_dir'];
            });

            $ret->singleton('path.storage', static function ($app) {
                $c = $app['lslim.container'];
                return $c['var_dir'];
            });

            $ret->singleton('path.config', static function ($app) {
                $c = $app['lslim.container'];
                return $c['config_dir'];
            });

            $ret->singleton('path.database', static function ($app) {
                $c = $app['lslim.container'];
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
