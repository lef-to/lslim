<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Cache\CacheManager;

class IlluminateCacheProvider implements  ServiceProviderInterface
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
        $container['cache'] = static function (Container $c) use ($config) {
            if ($config === null) {
                $config = $c['config_dir'] . DIRECTORY_SEPARATOR . $c['env'] . DIRECTORY_SEPARATOR . 'cache.php';
            }

            if (is_file($config)) {
                $config = require($config);
            } elseif (!is_array($config)) {
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

            $laravel = $c['laravel'];
            $laravel->make('config')['cache'] = $config;

            return new CacheManager($laravel);
        };
    }
}
