<?php
declare(strict_types=1);
namespace LSlim\Illuminate\Database;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\LazyCollection;
use Traversable;
use RuntimeException;

class Query
{
    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $chunkSize
     * @return Traversable
     */
    public static function chunkedIterator(QueryBuilder $query, $chunkSize): Traversable
    {
        if (empty($query->orders) && empty($query->unionOrders)) {
            throw new RuntimeException('You must specify an orderBy clause when using this function.');
        }

        $page = 1;
        do {
            $count = 0;
            $list = $query->forPage($page, $chunkSize)->get();

            foreach ($list as $item) {
                yield $item;
                $count++;
            }

            $page++;
        } while ($count == $chunkSize);
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $chunkSize
     * @return \Illuminate\Support\LazyCollection
     */
    public static function chunkedCollection(QueryBuilder $query, $chunkSize): LazyCollection
    {
        return new LazyCollection(static function () use ($query, $chunkSize) {
            yield from static::chunkedIterator($query, $chunkSize);
        });
    }
}