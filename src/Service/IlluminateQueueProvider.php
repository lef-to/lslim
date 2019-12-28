<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Queue\Capsule\Manager as Queue;

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
        $container['queue'] = static function (Container $c) use ($config) {
            $laravel = $c['laravel'];
            $queue = new Queue($laravel);

            if ($config === null) {
                $path = $c['config_dir'] . DIRECTORY_SEPARATOR . $c['env'] . DIRECTORY_SEPARATOR . 'queue.php';
                $config = require $path;
            }

            $laravel['config']['queue'] = $config;
            return $queue;
        };
    }
}
