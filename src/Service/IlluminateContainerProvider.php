<?php
declare(strict_types=1);
namespace LSlim\Service;

use Illuminate\Config\Repository;
use PImple\ServiceProviderInterface;
use Pimple\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use LSlim\Illuminate\Container as IlluminateContainer;

class IlluminateContainerProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['laravel'] = static function (Container $c) {
            $ret = new IlluminateContainer();

            $ret->instance('lslim.container', $c);
            $ret->instance('env', $c['env']);

            $ret->singleton('config', static function ($app) {
                return new Repository();
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

            $ret->singleton('path.stubs', static function ($app) {
                $c = $app['lslim.container'];
                if (isset($c['stubs_dir'])) {
                    return $c['stubs_dir'];
                }

                return rtrim($c['base_dir'], DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . 'stubs';
            });

            $ret->instance(ContainerContract::class, $ret);

            return $ret;
        };
    }
}
