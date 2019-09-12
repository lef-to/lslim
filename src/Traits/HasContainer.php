<?php
declare(strict_types=1);
namespace LSlim\Traits;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Capsule\Manager as Database;
use Illuminate\Queue\Capsule\Manager as Queue;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Intervention\Image\ImageManager;
use LSlim\Mail\MailerFactory;
use Closure;

trait HasContainer
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->container->get('logger');
    }

    protected function getDatabase(): Database
    {
        return $this->container->get('db');
    }

    protected function getQueue(): Queue
    {
        return $this->container->get('queue');
    }

    protected function getImageManager(): ImageManager
    {
        return $this->container->get('image_manager');
    }

    protected function getMailerFactory(): MailerFactory
    {
        return $this->container->get('mailer_factory');
    }

    protected function table($tableName, $connectionName = 'default'): QueryBuilder
    {
        return $this->getDatabase()->getConnection($connectionName)->table($tableName);
    }

    protected function transaction(callable $callback, $connectionName = 'default')
    {
        return $this->getDatabase()->getConnection($connectionName)->transaction(Closure::fromCallable($callback));
    }
}
