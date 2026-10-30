<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Session;

use Illuminate\Redis\Connections\Connection;
use LSlim\Container;
use LSlim\Service\IlluminateRedisProvider;
use LSlim\Session\RedisHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RedisHandlerTest extends TestCase
{
    private Container $container;
    private Connection $connection;
    private RedisHandler $handler;
    private string $prefix;
    private string $originalLifetime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLifetime = ini_get('session.gc_maxlifetime');
        ini_set('session.gc_maxlifetime', '120');
        $this->prefix = 'lslim:test:' . bin2hex(random_bytes(8)) . ':';
        $this->container = Container::setup('testing', ['logger' => new NullLogger()]);
        $this->container->register(new IlluminateRedisProvider([
            'client' => 'phpredis',
            'default' => [
                'host' => getenv('REDIS_HOST') ?: 'redis',
                'port' => getenv('REDIS_PORT') ?: 6379,
                'database' => 15,
            ],
        ]));
        $this->connection = $this->container->get('redis')->connection();
        $this->handler = new RedisHandler($this->container, $this->prefix);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->connection)) {
                // Only delete keys owned by this test; never flush a shared Redis database.
                foreach (['session-a', 'empty', 'old'] as $id) {
                    $this->connection->del([$this->prefix . $id]);
                }
                $this->connection->disconnect();
            }
        } finally {
            ini_set('session.gc_maxlifetime', $this->originalLifetime);
            parent::tearDown();
        }
    }

    public function testMissingSessionIsEmptyAndInvalid(): void
    {
        self::assertSame('', $this->handler->read('missing'));
        self::assertFalse($this->handler->validateId('missing'));
    }

    public function testSessionCanBeWrittenUpdatedAndDestroyed(): void
    {
        self::assertTrue($this->handler->write('session-a', '名前|s:6:"東京";'));
        self::assertSame('名前|s:6:"東京";', $this->handler->read('session-a'));
        self::assertTrue($this->handler->validateId('session-a'));
        self::assertTrue($this->handler->write('session-a', 'updated'));
        self::assertSame('updated', $this->handler->read('session-a'));
        self::assertGreaterThan(0, $this->connection->ttl($this->prefix . 'session-a'));
        self::assertLessThanOrEqual(120, $this->connection->ttl($this->prefix . 'session-a'));
        self::assertTrue($this->handler->destroy('session-a'));
        self::assertFalse($this->handler->validateId('session-a'));
        self::assertSame('', $this->handler->read('session-a'));
    }

    public function testEmptySessionDataRemainsAValidSession(): void
    {
        self::assertTrue($this->handler->write('empty', ''));
        self::assertTrue($this->handler->validateId('empty'));
        self::assertSame('', $this->handler->read('empty'));
    }

    public function testTimestampUpdateExtendsExpiryWithoutChangingData(): void
    {
        $this->connection->setex($this->prefix . 'old', 10, 'keep');
        self::assertTrue($this->handler->updateTimestamp('old', 'ignored'));
        self::assertGreaterThan(10, $this->connection->ttl($this->prefix . 'old'));
        self::assertLessThanOrEqual(120, $this->connection->ttl($this->prefix . 'old'));
        self::assertSame('keep', $this->handler->read('old'));
        self::assertFalse($this->handler->updateTimestamp('missing', 'ignored'));
    }

    public function testNonPositiveSessionLifetimeStillSetsAnExpiry(): void
    {
        ini_set('session.gc_maxlifetime', '0');
        self::assertTrue($this->handler->write('session-a', 'data'));
        self::assertSame(1, $this->connection->ttl($this->prefix . 'session-a'));
    }

    public function testProviderSharesTheConnectionWithTheIlluminateContainer(): void
    {
        self::assertSame($this->connection, $this->container->get('laravel')->make('redis.connection'));
    }
}
