<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Psr\Container\ContainerInterface;
use Illuminate\Cache\CacheManager;

class CacheFactory
{
    public static function create(ContainerInterface $c, $configPath): CacheManager
    {
        $laravel = $c->get('laravel');
        $config = (is_file($configPath))
            ? include $configPath
            : [
                'default' => 'files',
                'stores' => [
                    'files' => [
                        'driver' => 'file',
                    ]
                ]
            ];

        if (is_array($config['stores'])) {
            foreach ($config['stores'] as $name => &$cache) {
                $driver = $cache['driver'] ?? '';
                if ($driver == 'file' && !isset($cache['path'])) {
                    $cache['path'] = ltrim($c->get('cache_dir'), DIRECTORY_SEPARATOR)
                            . DIRECTORY_SEPARATOR . 'cache';
                }
            }
        }
        $laravel['config']['cache'] = $config;
        return new CacheManager($laravel);
    }
}
