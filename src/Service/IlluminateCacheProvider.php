<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Cache\CacheManager;
use LSlim\Illuminate\Container as IlluminateContainer;

class IlluminateCacheProvider implements ServiceProviderInterface
{
    /**
     * @var mixed
     */
    private $config;

    public function __construct($config = null)
    {
        $this->config = $config;
    }

    public function register(Container $container)
    {
        if (!isset($container['laravel'])) {
            $container->register(new IlluminateContainerProvider());
        }

        $config = $this->config;
        $container->extend('laravel', static function (IlluminateContainer $laravel, Container $c) use ($config) {
            if ($config === null) {
                $config = $c['config_dir'] . DIRECTORY_SEPARATOR . $c['env'] . DIRECTORY_SEPARATOR . 'cache.php';
            }

            if (!is_array($config)) {
                if (is_file($config)) {
                    $config = require($config);
                } else {
                    $path = (is_dir($config))
                        ? $config
                        : rtrim($c['cache_dir'], DIRECTORY_SEPARATOR)
                            . DIRECTORY_SEPARATOR
                            . 'data';

                    $config = [
                        'default' => 'file',
                        'stores' => [
                            'file' => [
                                'driver' => 'file',
                                'path' => $path
                            ]
                        ]
                    ];
                }
            }

            $laravel['config']['cache'] = $config;
            $laravel->singleton('cache', static function ($app) use ($c) {
                return $c['cache'];
            });

            $laravel->singleton('cache.store', static function ($app) {
                return $app['cache']->driver();
            });

            return $laravel;
        });

        $container['cache'] = static function (Container $c) {
            $laravel = $c['laravel'];
            return new CacheManager($laravel);
        };
    }
}
