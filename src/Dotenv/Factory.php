<?php

declare(strict_types=1);

namespace LSlim\Dotenv;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\RepositoryBuilder;

class Factory
{
    /**
     * @param string|string[]                           $paths
     * @param string|null                               $file
     *
     * @return \Dotenv\Dotenv
     */
    public static function create($paths, $file = null): Dotenv
    {
        $repository = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(EnvConstAdapter::class)
        ->immutable()
        ->make();

        return Dotenv::create($repository, $paths, $file);
    }
}
