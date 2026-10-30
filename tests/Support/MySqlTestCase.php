<?php

declare(strict_types=1);

namespace LSlim\Tests\Support;

use Illuminate\Database\Connection;
use LSlim\Container;
use LSlim\Service\IlluminateDatabaseProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

abstract class MySqlTestCase extends TestCase
{
    protected Container $container;
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('DB_TEST_DATABASE');
        if ($database === false || !str_ends_with($database, '_test')) {
            throw new RuntimeException('Set DB_TEST_DATABASE to a dedicated MySQL database ending in _test.');
        }
        $this->container = Container::setup('testing', ['logger' => new NullLogger()]);
        $this->container->register(new IlluminateDatabaseProvider([
            'default' => [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: 'mysql',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => $database,
                'username' => getenv('DB_USERNAME') ?: 'lslim',
                'password' => getenv('DB_PASSWORD') ?: 'lslim',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
        ]));
        $this->configureContainer();
        $this->connection = $this->container->get('db')->getConnection('default');
    }

    protected function configureContainer(): void
    {
    }

    protected function tearDown(): void
    {
        // Disconnecting also removes any temporary tables, even after a failed assertion.
        if (isset($this->connection)) {
            $this->connection->disconnect();
        }
        parent::tearDown();
    }
}
