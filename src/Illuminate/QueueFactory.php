<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Psr\Container\ContainerInterface;
use Illuminate\Queue\Capsule\Manager as Queue;

class QueueFactory
{
    public static function create(ContainerInterface $c, $configPath): Queue
    {
        $laravel = $c->get('laravel');
        $queue = new Queue($laravel);

        $config = require $configPath;
        $laravel['config']['queue'] = $config;

        return $queue;
    }
}
