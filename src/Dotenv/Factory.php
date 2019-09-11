<?php
declare(strict_types=1);
namespace LSlim\Dotenv;

use Dotenv\Dotenv;
use Dotenv\Environment\DotenvFactory;
use Dotenv\Environment\Adapter\PutenvAdapter;

class Factory
{
    /**
     * @param string|string[]                           $paths
     * @param string|null                               $file
     *
     * @return \Dotenv\Dotenv
     */
    public static function create($paths, $file = null)
    {
        $factory = new DotenvFactory([
            new PutenvAdapter()
        ]);

        return Dotenv::create($paths, $file, $factory);
    }
}
