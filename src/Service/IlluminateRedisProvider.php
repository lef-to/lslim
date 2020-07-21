<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Redis\RedisManager;
use LSlim\Illuminate\Container as IlluminateContainer;

class IlluminateRedisProvider implements ServiceProviderInterface
{
    /**
     * @var array|null
     */
    private $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config;
    }

    public function register(Container $container)
    {
        if (!isset($container['laravel'])) {
            $container->register(new IlluminateContainerProvider());
        }

        $config = $this->config;
        $container['redis'] = static function (Container $c) use ($config) {
            if ($config === null) {
                $path = $c['config_dir'] . DIRECTORY_SEPARATOR . $c['env'] . DIRECTORY_SEPARATOR . 'redis.php';
                $config = require $path;
            }

            return new RedisManager($c['laravel'], $config['client'] ?? 'phpredis', $config);
        };

        $container->extend('laravel', static function (IlluminateContainer $laravel, Container $c) {
            $laravel->singleton('redis', static function ($app) use ($c) {
                return $c['redis'];
            });

            $laravel->bind('redis.connection', static function ($app) {
                return $app['redis']->connection();
            });

            return $laravel;
        });
    }
}
