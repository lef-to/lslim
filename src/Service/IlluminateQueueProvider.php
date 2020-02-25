<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\QueueServiceProvider;

class IlluminateQueueProvider implements ServiceProviderInterface
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
        $container->extend('laravel', static function ($laravel, Container $c) use ($config) {
            if ($config === null) {
                $path = $c['config_dir'] . DIRECTORY_SEPARATOR . $c['env'] . DIRECTORY_SEPARATOR . 'queue.php';
                if (is_file($path)) {
                    $config = require $path;
                } else {
                    $config = [
                        'default' => 'database',
                        'connections' => [
                            'database' => [
                                'driver' => 'database',
                                'connection' => 'default',
                                'table' => 'job',
                                'queue' => 'default',
                                'retry_after' => 30
                            ]
                        ],
                        'failed' => [
                            'database' => 'default',
                            'table' => 'failed_job'
                        ]
                    ];
                }
            }
            $laravel['config']['queue'] = $config;

            return $laravel;
        });

        $container['queue'] = static function (Container $c) {
            $laravel = $c['laravel'];
            $manager = new QueueManager($laravel);

            $provider = new QueueServiceProvider($laravel);
            $provider->registerConnectors($manager);

            return $manager;
        };
    }
}
