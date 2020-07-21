<?php
declare(strict_types=1);
namespace LSlim\Service;

use PImple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Database\Capsule\Manager as Database;
use LSlim\Illuminate\Container as IlluminateContainer;

class IlluminateDatabaseProvider implements ServiceProviderInterface
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
        $container['db'] = static function (Container $c) use ($config) {
            $db = new Database($c['laravel']);

            if ($config === null) {
                $path = $c['config_dir'] . DIRECTORY_SEPARATOR . $c['env'] . DIRECTORY_SEPARATOR . 'db.php';
                $config = require $path;
            }

            foreach ($config as $k => $v) {
                $db->addConnection($v, $k);
            }

            return $db;
        };

        $container->extend('laravel', static function (IlluminateContainer $laravel, Container $c) {
            $laravel->singleton('db', static function ($app) use ($c) {
                return $c['db']->getDatabaseManager();
            });
            return $laravel;
        });
    }
}
