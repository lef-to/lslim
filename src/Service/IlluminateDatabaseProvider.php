<?php
declare(strict_types=1);
namespace LSlim\Service;

use PImple\Container;
use Pimple\ServiceProviderInterface;
use Illuminate\Database\Capsule\Manager as Database;
use Illuminate\Database\DatabaseTransactionsManager;
use LSlim\Illuminate\Container as IlluminateContainer;

class IlluminateDatabaseProvider implements ServiceProviderInterface
{
    public function __construct(private ?array $config = null)
    {
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
                $path   = $c['config_dir'] . DIRECTORY_SEPARATOR . 'database.php';
                $config = require $path;
            }

            foreach ($config as $k => $v) {
                $db->addConnection($v, $k);
            }

            return $db;
        };

        $container->extend('laravel', static function (IlluminateContainer $laravel, Container $c) {
            $laravel->singleton('db', static function ($app) {
                $c = $app['lslim.container'];
                return $c['db']->getDatabaseManager();
            });

            $laravel->bind('db.connection', static function ($app) {
                return $app['db']->connection();
            });

            $laravel->bind('db.schema', static function ($app) {
                return $app['db.connection']->getSchemaBuilder();
            });

            $laravel->singleton('db.transactions', static function ($app) {
                return new DatabaseTransactionsManager();
            });

            return $laravel;
        });
    }
}
