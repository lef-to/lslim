<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Session;

use LSlim\Session\DatabaseHandler;
use LSlim\Tests\Support\MySqlTestCase;
use Psr\Log\LoggerInterface;

final class DatabaseHandlerTest extends MySqlTestCase
{
    private string $table;
    private DatabaseHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->table = 'lslim_session_' . bin2hex(random_bytes(8));
        $this->connection->statement(
            "CREATE TEMPORARY TABLE `{$this->table}` (id VARCHAR(128) PRIMARY KEY, data LONGTEXT NOT NULL, ts BIGINT NOT NULL) ENGINE=InnoDB"
        );
        $this->handler = new DatabaseHandler($this->container, $this->table);
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
        self::assertSame(1, $this->connection->table($this->table)->count());
        self::assertTrue($this->handler->destroy('session-a'));
        self::assertSame('', $this->handler->read('session-a'));
        self::assertFalse($this->handler->validateId('session-a'));
    }

    public function testEmptySessionDataRemainsAValidSession(): void
    {
        self::assertTrue($this->handler->write('empty', ''));
        self::assertTrue($this->handler->validateId('empty'));
        self::assertSame('', $this->handler->read('empty'));
    }

    public function testTimestampUpdateKeepsDataAndRejectsMissingSessions(): void
    {
        $this->connection->table($this->table)->insert(['id' => 'old', 'data' => 'keep', 'ts' => 0]);
        $before = time();
        self::assertTrue($this->handler->updateTimestamp('old', 'ignored'));
        self::assertGreaterThanOrEqual($before, (int) $this->connection->table($this->table)->value('ts'));
        self::assertSame('keep', $this->handler->read('old'));
        self::assertFalse($this->handler->updateTimestamp('missing', 'ignored'));
    }

    public function testGarbageCollectionDeletesOnlyExpiredSessions(): void
    {
        $this->connection->table($this->table)->insert([
            ['id' => 'expired', 'data' => '', 'ts' => time() - 3600],
            ['id' => 'recent', 'data' => 'keep', 'ts' => time()],
        ]);
        self::assertSame(1, $this->handler->gc(60));
        self::assertFalse($this->handler->validateId('expired'));
        self::assertSame('keep', $this->handler->read('recent'));
    }

    public function testDatabaseFailureIsLoggedAndReportedAsFalse(): void
    {
        $this->connection->statement("DROP TEMPORARY TABLE `{$this->table}`");
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Failed to write session data.', self::arrayHasKey('exception')
        );
        $this->container['logger'] = $logger;
        self::assertFalse($this->handler->write('failed', 'data'));
    }

    public function testProviderSharesTheConnectionWithTheIlluminateContainer(): void
    {
        self::assertSame($this->connection, $this->container->get('laravel')->make('db.connection'));
    }
}
