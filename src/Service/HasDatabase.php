<?php
declare(strict_types=1);
namespace LSlim\Service;

use Illuminate\Database\Capsule\Manager as Database;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait HasDatabase
{
    abstract protected function getDatabase(): Database;

    protected function table($tableName, $connectionName = 'default'): QueryBuilder
    {
        return $this->getDatabase()->getConnection($connectionName)->table($tableName);
    }

    protected function transaction(callable $callback, $connectionName = 'default')
    {
        return $this->getDatabase()->getConnection($connectionName)->transaction($callback());
    }
}
