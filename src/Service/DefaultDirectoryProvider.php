<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class DefaultDirectoryProvider implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function register(Container $container)
    {
        if (!isset($container['base_dir'])) {
            $container['base_dir'] = $this->baseDir;
        }

        if (!isset($container['config_dir'])) {
            $container['config_dir'] = static function (Container $c) {
                return rtrim($c['base_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config';
            };
        }

        if (!isset($container['var_dir'])) {
            $container['var_dir'] = static function (Container $c) {
                return rtrim($c['base_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var';
            };
        }

        if (!isset($container['log_dir'])) {
            $container['log_dir'] = static function (Container $c) {
                return rtrim($c['var_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log';
            };
        }

        if (!isset($container['cache_dir'])) {
            $container['cache_dir'] = static function (Container $c) {
                return rtrim($c['var_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache';
            };
        }
    }
}
